# Laboratory 9 — Audit and automate secure configuration

Start from the recovered Laboratory 8 release (known-good version on
staging, or a **disposable clone** of it). This guide covers how to audit the
configuration against a version-controlled baseline, how to run an
approved configuration task twice, and how to detect and correct a
controlled drift.

---

## 1. Starting environment (record)

| Item | Value |
|---|---|
| Recovered revision (from Lab 8) | _fill in: `/health` revision and `git rev-parse HEAD`_ |
| Target | _fill in: staging URL, or the disposable clone (`-p lrs-clone`)_ |
| Laravel version | _fill in: `php artisan --version`_ |
| Driver / Reviewer | _fill in_ (swap before drift verification) |

## 2. Concepts

- **Infrastructure as code (IaC)** describes the *resources* (services,
  networks, volumes, ports, images) in versioned files. Here that is
  `compose.yaml`, `compose.staging.yaml` and `Dockerfile`: the `web`, `app`
  and `db` services, the private `backend` network, the `db_data` volume, and
  the single published web port.
- **Configuration as code** controls the *settings* inside those resources.
  Here that is `config/*.php` (which reads `env()`),
  `docker/nginx/default.conf` (document root), `docker/php/*`
  (production PHP settings), and the non-secret templates `.env.*.example`.
- **Declarative baseline**: `config-audit/baseline.example` states the
  approved end state: environment `staging`, debug off, document root
  `public/`, writable paths, service user, database scope, and exposed ports.
- **Imperative checks**: `config-audit/audit.sh` and
  `php artisan app:config-audit` are step-by-step commands that read the
  effective state and compare it with the baseline. The repeated task
  (`permission-task.sh`) is also imperative.
- **Idempotence**: running the same task again leaves the system in the same
  state and makes no further change. `permission-task.sh` checks the current
  mode first and only runs `chmod` when it differs, so the second run reports
  *no change* and the result matches run 1. This makes re-running safe after
  a partial failure or as a scheduled correction.
- **Drift**: the *effective* setting differs from the baseline. For example,
  someone sets `APP_DEBUG=true` on the server and refreshes the config cache.
  The audit detects drift because it compares the running value
  (`config('app.debug')`), not the file in Git.

## 3. Baseline (non-secret, version-controlled)

See [`config-audit/baseline.example`](../config-audit/baseline.example).
Update the paths, permissions and ports to the instructor's actual
requirements. It records:

| Control | Baseline value |
|---|---|
| Environment label | `staging` |
| Debug | `APP_DEBUG=false` |
| Document root | `/var/www/html/public` (only `public/` is served) |
| Writable paths | `storage`, `bootstrap/cache`; max mode `775`, owner `www-data`, nothing world-writable |
| Service identity | `www-data` (non-root PHP-FPM); Nginx runs unprivileged |
| Database access | `mysql` on private host `db:3306`; schema-scoped privileges only (no `*.*`, no `ALL`/`GRANT OPTION`) |
| Exposed services | `80`, `443` only; `3306`, `9000` and `6379` not public |
| TLS | Certificate hostname matches, valid for ≥ 14 days |

## 4. Audit script

- Application context: `app/Console/Commands/ConfigAudit.php`, run as
  `php artisan app:config-audit`. It reads the **effective** `config()` values,
  including a cached config.
- Wrapper: `config-audit/audit.sh` runs the application-context checks, then
  adds checks for the document root (Nginx config and HTTP probes for `/.env`,
  `/composer.json`, …), file ownership, listening ports, MySQL exposure, and
  the TLS hostname and expiry.

Each line of output shows the check name and `PASS`, `FAIL` or `UNVERIFIED`.
The script exits with **status 1 when any required check fails**. It reports
whether secrets are present or absent and never prints their values.

```sh
# inside the staging (or clone) app container
docker compose -p lrs-staging -f compose.yaml -f compose.staging.yaml exec app \
    sh config-audit/audit.sh --base-url https://staging.example.edu
# from the staging host, for host-level checks (ports, TLS)
sh config-audit/audit.sh --skip-app --base-url https://staging.example.edu
```

Example output:

```text
environment_label            PASS       expected=staging actual=staging
debug_off                    PASS       expected=false actual=false
app_key_present              PASS       present
database_access_scope        PASS       schema-scoped grants only
writable_storage             PASS       mode=775 max=775
service_identity             PASS       expected=www-data actual=www-data
document_root_http           PASS       project files not served
exposed_ports                UNVERIFIED ss/netstat unavailable - inspect host firewall manually
tls_certificate              PASS       hostname match; valid > 14d; notAfter=...
# overall: PASS
```

Mark any control you cannot reach as **UNVERIFIED** and explain why. Never
mark it PASS.

## 5. Permission and exposure checklist

| Control | Expected condition | How checked | Observed | Result | Evidence |
|---|---|---|---|---|---|
| Debug mode | `APP_DEBUG=false` effective | `debug_off` | | | Fig. |
| Document root | Nginx `root /var/www/html/public`; `/.env`, `/composer.json`, `/artisan` not served | `document_root_config`, `document_root_http` | | | Fig. |
| Writable paths | `storage/`, `bootstrap/cache/` writable by `www-data`, mode ≤ 775, no world-write | `writable_*`, `owner_*`, `stat -c "%a %U %G"` | | | Fig. |
| Service privileges | PHP-FPM runs as `www-data` (non-root); `no-new-privileges`, `cap_drop: ALL` | `service_identity`, `docker compose exec app id` | | | Fig. |
| Database access | MySQL only on the private `backend` network (no `ports:`); app account limited to its schema | `database_private_host`, `database_access_scope`, `mysql_not_public` | | | Fig. |
| Firewall and services | Only 80/443 public; no unnecessary services | `exposed_ports`, `ss -ltnp`, firewall rules (instructor) | | | Fig. |
| TLS | Hostname matches, not expiring soon | `tls_certificate` | | | Fig. |
| Patch responsibility | Base images (`php:8.4-fpm-alpine`, `nginx-unprivileged`, `mysql:8.4`) rebuilt monthly or on advisories; host OS patched | Instructor / team | | | — |
| Secret handling | `.env.staging` only on the host (mode 600); never in Git or images | `env_file_in_release`, `git ls-files`, `.dockerignore` | | | Fig. |

**Responsibilities.** Fill these in from your instructor's setup.
- Certificate renewal: _instructor / platform (e.g. automatic ACME on the reverse proxy)_
- Firewall controls: _instructor / host administrator_
- OS and base-image patching: _host administrator (OS); team rebuilds images (application)_

## 6. Repeatable configuration task (run twice)

On the assigned Linux target, use the instructor-created scratch directory,
owned by the lab account:

```sh
stat -c "%a %U %G" ./lab9-permissions-demo        # before
sh config-audit/permission-task.sh ./lab9-permissions-demo 750   # run 1
sh config-audit/permission-task.sh ./lab9-permissions-demo 750   # run 2
```

| Stage | Mode | Owner | Group | Action reported |
|---|---|---|---|---|
| Before | | | | — |
| After run 1 | 750 | | | `chmod 750 applied` (or no change) |
| After run 2 | 750 | same | same | `no change (already 750)` |

The run 2 result must equal the run 1 result. That equality is the evidence
of idempotence. Each run also appends a line to
`config-audit/permission-task.log`, which is git-ignored.

**On Windows (this team's PC).** `chmod` and `stat` have no meaning on NTFS,
so run the task inside the staging app container, which is Linux:

```sh
DC="docker compose -p lrs-staging -f compose.yaml -f compose.staging.yaml"
$DC exec app sh -c 'mkdir -p /tmp/lab9-permissions-demo && chmod 755 /tmp/lab9-permissions-demo'   # scratch dir
$DC exec app sh -c 'stat -c "%a %U %G" /tmp/lab9-permissions-demo'
$DC exec app sh -c 'LOG=/tmp/task.log sh config-audit/permission-task.sh /tmp/lab9-permissions-demo 750'   # run 1
$DC exec app sh -c 'LOG=/tmp/task.log sh config-audit/permission-task.sh /tmp/lab9-permissions-demo 750'   # run 2
```

### Corrective task found by the audit: least-privilege database account

The first staging audit reported `database_access_scope FAIL`, because the
MySQL image gives the app account `ALL PRIVILEGES` on its schema.
`config-audit/db-least-privilege.sh` replaces that grant with exactly the
baseline privileges, `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX,
DROP, REFERENCES`, on that schema only. It is idempotent: run 2 shows the
same grants as run 1.

```sh
cd C:/lrs-staging   # in Git Bash; run with the repo's config-audit folder
sh /c/xampp/htdocs/laravel-request-system/config-audit/db-least-privilege.sh \
   "docker compose -p lrs-staging -f compose.yaml -f compose.staging.yaml"   # run 1, then run 2
```

Record the grants before and after each run, then rerun the audit and
confirm `database_access_scope PASS`.

## 7. Drift detection and correction (disposable clone only)

```sh
# 0. Baseline audit -> debug_off PASS
docker compose -p lrs-clone -f compose.yaml -f compose.staging.yaml exec app php artisan app:config-audit

# 1. Introduce controlled drift on the CLONE: set APP_DEBUG=true in its .env.staging
#    then refresh effective config (the entrypoint re-runs config:cache on start):
docker compose -p lrs-clone -f compose.yaml -f compose.staging.yaml up -d --no-deps --force-recreate app
#    (or, inside the running service:  php artisan config:cache)

# 2. Audit again -> debug_off FAIL, exit status 1 (expected)
docker compose -p lrs-clone ... exec app php artisan app:config-audit ; echo "exit=$?"

# 3. Restore APP_DEBUG=false, refresh again, rerun -> debug_off PASS, exit 0
```

**Why the cache refresh matters.** Laravel config files read `env()`, and the
application reads settings only through `config()`. When the configuration
is cached (`php artisan config:cache`, run by the container entrypoint),
editing the env file changes nothing until the cache is rebuilt in the
service context. Always verify the **running** value with the audit, not the
file alone.

**Local rehearsal (XAMPP).** Set `APP_DEBUG=true` in `.env`, run
`php artisan config:cache`, then run
`php artisan app:config-audit --baseline=config-audit/baseline.local.example`.
`debug_off` shows FAIL. Restore the value, run `config:cache` again, and the
check shows PASS. Finish with `php artisan config:clear`.

## 8. Secrets lifecycle on staging

| Stage | How |
|---|---|
| **Provision** | The instructor or driver creates `.env.staging` and `.env.db` directly on the staging host from the templates. `APP_KEY` is generated once (`php artisan key:generate --show`). DB passwords are random. GitHub deploy credentials (SSH key, known_hosts, smoke-test account passwords) are stored as **environment secrets** on `staging`. |
| **Restrict** | Files are `chmod 600`, owned by the deploy account, and excluded from Git (`.gitignore`) and images (`.dockerignore`). MySQL is unreachable from outside. The app DB user holds schema-only grants. GitHub environment secrets are only available to jobs that pass the approval gate. |
| **Rotate** | Change the DB password (`ALTER USER`), update `.env.staging`, recreate `app`, and check that `database_reachable PASS`. Rotate the SSH deploy key each term. Do not rotate `APP_KEY` casually, because that invalidates sessions and encrypted data. Plan it as its own change. |
| **Revoke** | Remove the GitHub secret or deploy key, drop or lock the DB user, and remove the partner from the environment reviewers. If a secret is exposed, revoke or rotate it first, then remove it from the repository and its history. Deleting the file alone is not enough. |
| **Audit** | GitHub keeps the environment deployment history (who approved, when) and the audit log. `.releases/deploy.log` on the host. `app_key_present` and `database_password_present` report presence only. |

**Who can access what:** _fill in, e.g. instructor (host root, secrets);
driver (deploy account via workflow only); approver (approve deployments,
no secret read access); GitHub Actions (secrets, only inside the approved job)._

The reviewer verifies the baseline, both task runs and the drift
correction, and signs off here: _name, date_.

## 9. What to commit

Commit only `config-audit/` (the scripts and baselines, not logs),
`app/Console/Commands/ConfigAudit.php`, and the sanitized docs. **Never commit**
`.env*` files (except templates), keys, tokens, database exports, or
`bootstrap/cache/config.php`. Record the reviewed commit SHA and the PR link.
