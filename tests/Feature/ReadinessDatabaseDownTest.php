<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Laboratory 10 - when the database is down, readiness returns 503 with a
 * generic status (no exception details) while liveness still returns 200.
 */
class ReadinessDatabaseDownTest extends TestCase
{
    public function test_readiness_503_and_liveness_200_when_database_is_down(): void
    {
        DB::shouldReceive('select')->andThrow(new RuntimeException('SQLSTATE[HY000] [2002] connection refused (secret detail)'));
        DB::shouldReceive('getConnections')->andReturn([]);

        $this->get('/health/ready')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable'])
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('secret detail');

        $this->get('/health/live')->assertOk();
    }
}
