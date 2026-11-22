<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Automated version of the Laboratory 3 access and input test matrix.
 * T09 (CSRF) must still be tested live: Laravel skips CSRF checks in tests.
 */
class ServiceRequestAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $studentA;

    private User $studentB;

    private User $admin;

    private ServiceRequest $requestA;

    private ServiceRequest $requestB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->studentA = User::factory()->create(['name' => 'Student A']);
        $this->studentB = User::factory()->create(['name' => 'Student B']);
        $this->admin = User::factory()->admin()->create(['name' => 'Admin']);

        $this->requestA = ServiceRequest::factory()->create(['user_id' => $this->studentA->id, 'item_name' => 'Owned by A']);
        $this->requestB = ServiceRequest::factory()->create(['user_id' => $this->studentB->id, 'item_name' => 'Owned by B']);
    }

    public function test_t01_guest_is_redirected_and_sees_no_data(): void
    {
        $this->get(route('requests.index'))->assertRedirect(route('login'));
        $this->get(route('requests.show', $this->requestA))->assertRedirect(route('login'));
        $this->post(route('requests.store'), [])->assertRedirect(route('login'));
    }

    public function test_t02_students_see_only_their_own_records(): void
    {
        $this->actingAs($this->studentA)->get(route('requests.index'))
            ->assertOk()->assertSee('Owned by A')->assertDontSee('Owned by B');

        $this->actingAs($this->studentB)->get(route('requests.index'))
            ->assertOk()->assertSee('Owned by B')->assertDontSee('Owned by A');

        $this->actingAs($this->studentA)->get(route('requests.show', $this->requestA))->assertOk();
    }

    public function test_t02_search_and_status_filters_stay_scoped(): void
    {
        $this->actingAs($this->studentA)->get(route('requests.index', ['search' => 'Owned', 'status' => 'pending']))
            ->assertOk()->assertSee('Owned by A')->assertDontSee('Owned by B');
    }

    public function test_t03_other_students_record_returns_403(): void
    {
        $this->actingAs($this->studentA)->get(route('requests.show', $this->requestB))
            ->assertForbidden()->assertDontSee('Owned by B');

        $this->actingAs($this->studentB)->get(route('requests.show', $this->requestA))
            ->assertForbidden()->assertDontSee('Owned by A');
    }

    public function test_t04_student_status_patch_is_denied_and_status_unchanged(): void
    {
        $this->actingAs($this->studentA)
            ->patch(route('requests.updateStatus', $this->requestA), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertSame('pending', $this->requestA->fresh()->status);
    }

    public function test_t05_admin_lists_views_and_updates_status(): void
    {
        $this->actingAs($this->admin)->get(route('requests.index'))
            ->assertOk()->assertSee('Owned by A')->assertSee('Owned by B');

        $this->actingAs($this->admin)->get(route('requests.show', $this->requestB))->assertOk();

        $this->actingAs($this->admin)
            ->patch(route('requests.updateStatus', $this->requestB), ['status' => 'approved'])
            ->assertRedirect(route('requests.show', $this->requestB));

        $this->assertSame('approved', $this->requestB->fresh()->status);
    }

    public function test_t06_invalid_quantity_or_blank_item_is_rejected(): void
    {
        $before = ServiceRequest::count();

        foreach ([0, -1, 1.5, 'abc'] as $quantity) {
            $this->actingAs($this->studentA)
                ->post(route('requests.store'), ['item_name' => 'Pen', 'quantity' => $quantity, 'purpose' => 'Test'])
                ->assertSessionHasErrors('quantity');
        }

        $this->actingAs($this->studentA)
            ->post(route('requests.store'), ['item_name' => '', 'quantity' => 1, 'purpose' => 'Test'])
            ->assertSessionHasErrors('item_name');

        $this->assertSame($before, ServiceRequest::count());
    }

    public function test_t07_spoofed_fields_are_rejected(): void
    {
        $before = ServiceRequest::count();

        foreach (['user_id' => $this->studentB->id, 'status' => 'approved', 'is_admin' => 1, 'role' => 'admin'] as $field => $value) {
            $this->actingAs($this->studentA)
                ->post(route('requests.store'), ['item_name' => 'Pen', 'quantity' => 1, 'purpose' => 'Test', $field => $value])
                ->assertSessionHasErrors($field);
        }

        $this->assertSame($before, ServiceRequest::count());
        $this->assertSame('student', $this->studentA->fresh()->role);
    }

    public function test_valid_request_is_owned_by_signed_in_student_and_pending(): void
    {
        $this->actingAs($this->studentA)
            ->post(route('requests.store'), ['item_name' => 'HDMI Adapter STAGE-LIB-08', 'quantity' => 1, 'purpose' => 'Smoke test'])
            ->assertRedirect();

        $created = ServiceRequest::where('item_name', 'HDMI Adapter STAGE-LIB-08')->firstOrFail();
        $this->assertSame($this->studentA->id, $created->user_id);
        $this->assertSame($this->studentA->email, $created->requester_email);
        $this->assertSame('pending', $created->status);
    }

    public function test_t08_markup_is_escaped_and_apostrophe_stored(): void
    {
        $this->actingAs($this->studentA)
            ->post(route('requests.store'), ['item_name' => "<b>LAB3</b> O'Brien", 'quantity' => 1, 'purpose' => "It's needed"]);

        $created = ServiceRequest::where('item_name', "<b>LAB3</b> O'Brien")->firstOrFail();

        $this->actingAs($this->studentA)->get(route('requests.show', $created))
            ->assertOk()
            ->assertSee('&lt;b&gt;LAB3&lt;/b&gt;', false)
            ->assertDontSee('<b>LAB3</b>', false);
    }

    public function test_t10_admin_invalid_status_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('requests.updateStatus', $this->requestA), ['status' => 'done'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $this->requestA->fresh()->status);
    }

    public function test_admin_cannot_create_student_requests(): void
    {
        $this->actingAs($this->admin)
            ->post(route('requests.store'), ['item_name' => 'Pen', 'quantity' => 1, 'purpose' => 'Test'])
            ->assertForbidden();
    }
}
