<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Laboratory 8: the health check must report "fail" (HTTP 503) rather than
 * crash when the database is unreachable, and must not depend on sessions.
 */
class HealthDatabaseDownTest extends TestCase
{
    public function test_health_reports_fail_with_503_when_database_is_down(): void
    {
        DB::shouldReceive('connection->select')->andThrow(new RuntimeException('database down'));
        DB::shouldReceive('getConnections')->andReturn([]);

        $this->getJson(route('health'))
            ->assertStatus(503)
            ->assertJson(['status' => 'fail', 'checks' => ['database' => 'fail']])
            ->assertJsonMissingPath('exception');
    }
}
