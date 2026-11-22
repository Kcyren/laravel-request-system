<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Laboratory 9 - application-context configuration audit.
 *
 * Compares the EFFECTIVE Laravel configuration (config(), as the running
 * service sees it, including a cached config) with the declarative,
 * non-secret baseline in config-audit/baseline.example.
 *
 * Output: one line per check -> "<check_name> PASS|FAIL|UNVERIFIED [note]".
 * Exit code 1 when any REQUIRED check fails.
 *
 * Secrets are never printed: for APP_KEY / DB password only presence or
 * absence is reported. Raw environment variables and cached config are
 * never dumped.
 */
class ConfigAudit extends Command
{
    protected $signature = 'app:config-audit
        {--baseline=config-audit/baseline.example : Path to the non-secret baseline file}
        {--json : Output JSON instead of text}';

    protected $description = 'Audit effective Laravel configuration against the approved baseline (Lab 9)';

    /** @var array<int, array{name:string,status:string,required:bool,note:string}> */
    private array $results = [];

    public function handle(): int
    {
        $baselinePath = base_path($this->option('baseline'));

        if (! is_file($baselinePath)) {
            $this->error("Baseline not found: {$this->option('baseline')}");

            return self::FAILURE;
        }

        $baseline = $this->parseBaseline($baselinePath);

        $this->checkEnvironment($baseline);
        $this->checkDebug($baseline);
        $this->checkAppKey();
        $this->checkUrlScheme($baseline);
        $this->checkSecureCookie($baseline);
        $this->checkLogLevel($baseline);
        $this->checkDatabase($baseline);
        $this->checkWritablePaths($baseline);
        $this->checkServiceIdentity($baseline);
        $this->checkEnvFile($baseline);
        $this->checkConfigCache($baseline);

        $this->report();

        $failedRequired = collect($this->results)->contains(fn ($r) => $r['required'] && $r['status'] === 'FAIL');

        return $failedRequired ? self::FAILURE : self::SUCCESS;
    }

    /** Parse KEY=VALUE lines; comments (#) and blanks ignored. Never executed as code. */
    private function parseBaseline(string $path): array
    {
        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $values[$key] = trim($value, "\"'");
        }

        return $values;
    }

    private function add(string $name, string $status, bool $required = true, string $note = ''): void
    {
        $this->results[] = compact('name', 'status', 'required', 'note');
    }

    private function bool(?string $value): ?bool
    {
        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function checkEnvironment(array $b): void
    {
        $expected = $b['APP_ENV_LABEL'] ?? null;
        $actual = app()->environment();

        $expected === null
            ? $this->add('environment_label', 'UNVERIFIED', true, 'no baseline value')
            : $this->add('environment_label', $actual === $expected ? 'PASS' : 'FAIL', true, "expected={$expected} actual={$actual}");
    }

    private function checkDebug(array $b): void
    {
        $expected = $this->bool($b['APP_DEBUG'] ?? 'false') ?? false;
        $actual = (bool) config('app.debug');

        $this->add('debug_off', $actual === $expected ? 'PASS' : 'FAIL', true,
            'expected='.($expected ? 'true' : 'false').' actual='.($actual ? 'true' : 'false'));
    }

    private function checkAppKey(): void
    {
        // Presence only - the key itself is never printed.
        $this->add('app_key_present', filled(config('app.key')) ? 'PASS' : 'FAIL', true,
            filled(config('app.key')) ? 'present' : 'absent');
    }

    private function checkUrlScheme(array $b): void
    {
        $expected = $b['APP_URL_SCHEME'] ?? null;
        $actual = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'none';

        $expected === null
            ? $this->add('app_url_scheme', 'UNVERIFIED', false, 'no baseline value')
            : $this->add('app_url_scheme', $actual === $expected ? 'PASS' : 'FAIL', true, "expected={$expected} actual={$actual}");
    }

    private function checkSecureCookie(array $b): void
    {
        $expected = $this->bool($b['SESSION_SECURE_COOKIE'] ?? null);
        if ($expected === null) {
            $this->add('session_secure_cookie', 'UNVERIFIED', false, 'no baseline value');

            return;
        }
        $actual = (bool) config('session.secure');
        $this->add('session_secure_cookie', $actual === $expected ? 'PASS' : 'FAIL', true,
            'expected='.var_export($expected, true).' actual='.var_export($actual, true));
    }

    private function checkLogLevel(array $b): void
    {
        $allowed = array_filter(array_map('trim', explode(',', $b['LOG_LEVEL_ALLOWED'] ?? '')));
        if ($allowed === []) {
            $this->add('log_level', 'UNVERIFIED', false, 'no baseline value');

            return;
        }
        $channel = config('logging.default');
        $level = config("logging.channels.{$channel}.level") ?? config('logging.channels.single.level', 'debug');
        $this->add('log_level', in_array($level, $allowed, true) ? 'PASS' : 'FAIL', false, "actual={$level}");
    }

    private function checkDatabase(array $b): void
    {
        $expectedDriver = $b['DB_CONNECTION'] ?? null;
        $driver = config('database.default');
        $this->add('database_driver', $expectedDriver === null || $expectedDriver === $driver ? 'PASS' : 'FAIL', true,
            "expected={$expectedDriver} actual={$driver}");

        $expectedHost = $b['DB_EXPECTED_HOST'] ?? null;
        if ($expectedHost !== null) {
            $host = (string) config("database.connections.{$driver}.host");
            $this->add('database_private_host', $host === $expectedHost ? 'PASS' : 'FAIL', true,
                $host === $expectedHost ? "host matches baseline ({$expectedHost})" : 'host differs from baseline');
        }

        $this->add('database_password_present',
            filled(config("database.connections.{$driver}.password")) ? 'PASS' : 'FAIL',
            $driver !== 'sqlite',
            filled(config("database.connections.{$driver}.password")) ? 'present' : 'absent');

        try {
            DB::connection()->select('select 1');
            $this->add('database_reachable', 'PASS');
        } catch (Throwable) {
            $this->add('database_reachable', 'FAIL', true, 'connection failed (details suppressed)');
            $this->add('database_access_scope', 'UNVERIFIED', false, 'database unreachable');

            return;
        }

        $this->checkDatabaseScope($driver, $b);
    }

    /**
     * Least privilege: the runtime account must not hold global (*.*)
     * privileges or ALL PRIVILEGES / GRANT OPTION, and may only use the
     * privileges listed in DB_ALLOWED_PRIVILEGES.
     */
    private function checkDatabaseScope(string $driver, array $b): void
    {
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->add('database_access_scope', 'UNVERIFIED', false, "not checkable for {$driver}");

            return;
        }

        try {
            $grants = collect(DB::select('SHOW GRANTS FOR CURRENT_USER()'))
                ->map(fn ($row) => (string) array_values((array) $row)[0]);
        } catch (Throwable) {
            $this->add('database_access_scope', 'UNVERIFIED', false, 'grants not readable');

            return;
        }

        $allowed = array_map('strtoupper', array_filter(array_map('trim', explode(',', $b['DB_ALLOWED_PRIVILEGES'] ?? ''))));
        $problems = [];

        foreach ($grants as $grant) {
            if (! preg_match('/^GRANT (.+) ON (\S+) TO /i', $grant, $m)) {
                continue;
            }
            $privileges = array_map(fn ($p) => strtoupper(trim($p)), explode(',', $m[1]));
            $scope = str_replace('`', '', $m[2]);

            if ($privileges === ['USAGE']) {
                continue;
            }
            if ($scope === '*.*') {
                $problems[] = 'global privileges';
            }
            if (in_array('ALL PRIVILEGES', $privileges, true) || stripos($grant, 'WITH GRANT OPTION') !== false) {
                $problems[] = 'ALL/GRANT OPTION';
            }
            if ($allowed !== [] && array_diff($privileges, $allowed, ['ALL PRIVILEGES']) !== []) {
                $problems[] = 'privilege outside baseline';
            }
        }

        $problems = array_unique($problems);
        $this->add('database_access_scope', $problems === [] ? 'PASS' : 'FAIL', true,
            $problems === [] ? 'schema-scoped grants only' : implode('; ', $problems));
    }

    private function checkWritablePaths(array $b): void
    {
        $paths = array_filter(array_map('trim', explode(',', $b['WRITABLE_PATHS'] ?? 'storage,bootstrap/cache')));
        $maxMode = isset($b['WRITABLE_MODE_MAX']) ? octdec($b['WRITABLE_MODE_MAX']) : 0775;
        $windows = PHP_OS_FAMILY === 'Windows';

        foreach ($paths as $relative) {
            $name = 'writable_'.str_replace(['/', '\\'], '_', $relative);
            $path = base_path($relative);

            if (! is_dir($path)) {
                $this->add($name, 'FAIL', true, 'missing');

                continue;
            }
            if (! is_writable($path)) {
                $this->add($name, 'FAIL', true, 'not writable by service identity');

                continue;
            }
            if ($windows) {
                $this->add($name, 'UNVERIFIED', true, 'writable; POSIX mode not checkable on Windows');

                continue;
            }
            $mode = fileperms($path) & 0777;
            $worldWritable = ($mode & 0002) !== 0;
            $ok = ! $worldWritable && ($mode & ~$maxMode) === 0;
            $this->add($name, $ok ? 'PASS' : 'FAIL', true, sprintf('mode=%o max=%o', $mode, $maxMode));
        }
    }

    private function checkServiceIdentity(array $b): void
    {
        $expected = $b['SERVICE_USER'] ?? null;

        if (! function_exists('posix_geteuid')) {
            $this->add('service_identity', 'UNVERIFIED', false, 'posix extension unavailable on this platform');

            return;
        }

        $uid = posix_geteuid();
        $name = posix_getpwuid($uid)['name'] ?? (string) $uid;

        if ($uid === 0) {
            $this->add('service_identity', 'FAIL', true, 'running as root');

            return;
        }

        $expected === null
            ? $this->add('service_identity', 'PASS', true, "non-root ({$name})")
            : $this->add('service_identity', $name === $expected ? 'PASS' : 'FAIL', true, "expected={$expected} actual={$name}");
    }

    private function checkEnvFile(array $b): void
    {
        $expected = $b['ENV_FILE_IN_RELEASE'] ?? null;
        if ($expected === null) {
            return;
        }
        $actual = is_file(base_path('.env')) ? 'present' : 'absent';
        $this->add('env_file_in_release', $actual === $expected ? 'PASS' : 'FAIL', false, "expected={$expected} actual={$actual}");
    }

    private function checkConfigCache(array $b): void
    {
        $expected = $this->bool($b['CONFIG_CACHED'] ?? null);
        $actual = app()->configurationIsCached();

        $expected === null
            ? $this->add('config_cached', 'UNVERIFIED', false, 'actual='.var_export($actual, true))
            : $this->add('config_cached', $actual === $expected ? 'PASS' : 'FAIL', false, 'actual='.var_export($actual, true));
    }

    private function report(): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        foreach ($this->results as $r) {
            $this->line(sprintf('%-28s %-10s %s%s',
                $r['name'], $r['status'], $r['required'] ? '' : '(advisory) ', $r['note']));
        }
    }
}
