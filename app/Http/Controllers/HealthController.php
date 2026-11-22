<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Release health check (Laboratory 8).
 *
 * Reports only non-secret facts: overall status, version, revision,
 * environment label and whether the database answered. It never returns
 * configuration values, credentials, hostnames or exception messages.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $databaseOk = true;

        try {
            DB::connection()->select('select 1');
        } catch (Throwable) {
            $databaseOk = false;
        }

        $healthy = $databaseOk;

        return response()->json([
            'status' => $healthy ? 'ok' : 'fail',
            'app' => config('app.name'),
            'version' => config('release.version'),
            'revision' => config('release.revision'),
            'environment' => app()->environment(),
            'checks' => [
                'database' => $databaseOk ? 'ok' : 'fail',
            ],
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
