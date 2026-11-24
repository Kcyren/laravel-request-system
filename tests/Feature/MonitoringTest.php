<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Laboratory 10 - liveness/readiness routes, request identifier and
 * structured logging of request creation and status updates.
 */
class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_returns_200_without_details(): void
    {
        $this->get('/health/live')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_readiness_returns_200_when_database_is_reachable(): void
    {
        $this->get('/health/ready')->assertOk()->assertExactJson(['status' => 'ready']);
    }

    public function test_every_response_has_a_request_id_header(): void
    {
        $this->get('/health/live')->assertHeader('X-Request-Id');
        $this->get('/health/live', ['X-Request-Id' => 'lab10-test-1234'])->assertHeader('X-Request-Id', 'lab10-test-1234');
    }

    public function test_request_creation_is_logged_after_success_without_personal_data(): void
    {
        Log::spy();
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('requests.store'), [
            'item_name' => 'HDMI Adapter MON-LAB-10', 'quantity' => 1, 'purpose' => 'Secret purpose text',
        ])->assertRedirect();

        $record = ServiceRequest::firstOrFail();
        Log::shouldHaveReceived('info')->withArgs(function ($event, $context) use ($record) {
            return $event === 'request.created'
                && $context['record_id'] === $record->id
                && $context['outcome'] === 'success'
                && ! empty($context['request_id'])
                && ! array_key_exists('purpose', $context)
                && ! array_key_exists('requester_email', $context);
        })->once();
    }

    public function test_failed_creation_is_not_logged_as_success(): void
    {
        Log::spy();
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('requests.store'), ['item_name' => '', 'quantity' => 0, 'purpose' => ''])
            ->assertSessionHasErrors();

        Log::shouldNotHaveReceived('info', ['request.created', \Mockery::any()]);
    }

    public function test_admin_status_update_is_logged_and_student_update_is_not(): void
    {
        Log::spy();
        $student = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $record = ServiceRequest::factory()->create(['user_id' => $student->id]);

        $this->actingAs($student)->patch(route('requests.updateStatus', $record), ['status' => 'approved'])->assertForbidden();
        Log::shouldNotHaveReceived('info', ['request.status_updated', \Mockery::any()]);

        $this->actingAs($admin)->patch(route('requests.updateStatus', $record), ['status' => 'approved'])->assertRedirect();
        Log::shouldHaveReceived('info')->withArgs(fn ($event, $context) => $event === 'request.status_updated'
            && $context['record_id'] === $record->id && $context['status'] === 'approved' && $context['outcome'] === 'success')->once();
    }
}
