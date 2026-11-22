<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laboratory 8: health check / revision reporting and the controlled-fault switch.
 */
class ReleaseHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_version_revision_and_database(): void
    {
        config(['release.version' => '9.9.9', 'release.revision' => 'abc123']);

        $this->getJson(route('health'))
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'version' => '9.9.9',
                'revision' => 'abc123',
                'checks' => ['database' => 'ok'],
            ])
            ->assertJsonMissingPath('config');
    }

    public function test_faulty_artifact_disables_request_creation(): void
    {
        config(['release.lab_fault' => true]);
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('requests.index'))
            ->assertOk()->assertSee('temporarily unavailable');

        $this->actingAs($student)
            ->post(route('requests.store'), ['item_name' => 'HDMI Adapter', 'quantity' => 1, 'purpose' => 'x'])
            ->assertStatus(503);

        $this->assertSame(0, ServiceRequest::count());
    }
}
