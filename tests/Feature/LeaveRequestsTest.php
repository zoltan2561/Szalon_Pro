<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create();
    }

    public function test_staff_requests_are_private_and_do_not_close_the_day_before_approval(): void
    {
        $staff = Staff::factory()->create();
        $worker = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        $this->actingAs($worker)->post(route('admin.leave.store'), ['starts_on' => '2026-10-26', 'ends_on' => '2026-10-27', 'note' => 'Két szabadnapot kérek.'])->assertSessionHasNoErrors();
        $leave = LeaveRequest::firstOrFail();
        $this->assertSame($staff->id, $leave->staff_id);
        $this->assertDatabaseCount('work_exceptions', 0);
        $other = LeaveRequest::factory()->create(['note' => 'Másik dolgozó magánkérése']);
        $this->get(route('admin.leave.index'))->assertOk()->assertSee('Két szabadnapot kérek.')->assertDontSee('Másik dolgozó magánkérése');
        $this->post(route('admin.leave.review', $other), ['action' => 'withdraw'])->assertForbidden();
        $this->post(route('admin.leave.review', $leave), ['action' => 'approve'])->assertForbidden();
    }

    public function test_owner_approval_closes_every_requested_day_and_revocation_preserves_manual_closures(): void
    {
        $leave = LeaveRequest::factory()->create(['starts_on' => '2026-10-26', 'ends_on' => '2026-10-28']);
        WorkException::factory()->create(['staff_id' => $leave->staff_id, 'date' => '2026-10-26', 'hours' => []]);
        $this->actingAs(User::factory()->create(['role' => 'owner']))->post(route('admin.leave.review', $leave), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertDatabaseCount('work_exceptions', 3);
        $this->post(route('admin.leave.review', $leave), ['action' => 'withdraw'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('work_exceptions', 1);
        $this->assertDatabaseHas('work_exceptions', ['date' => '2026-10-26']);
    }

    public function test_approval_cannot_silently_displace_existing_bookings(): void
    {
        $booking = Booking::factory()->create(['starts_at' => '2026-10-26 08:00:00', 'ends_at' => '2026-10-26 09:00:00', 'busy_from' => '2026-10-26 08:00:00', 'busy_until' => '2026-10-26 09:00:00']);
        $leave = LeaveRequest::factory()->create(['staff_id' => $booking->staff_id, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26']);
        $this->actingAs(User::factory()->create(['role' => 'owner']))->post(route('admin.leave.review', $leave), ['action' => 'approve'])->assertSessionHasErrors('action');
        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertDatabaseCount('work_exceptions', 0);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_overlapping_requests_and_staff_impersonation_are_rejected(): void
    {
        $leave = LeaveRequest::factory()->create(['starts_on' => '2026-10-26', 'ends_on' => '2026-10-27']);
        $worker = User::factory()->create(['role' => 'staff', 'staff_id' => $leave->staff_id]);
        $this->actingAs($worker)->post(route('admin.leave.store'), ['starts_on' => '2026-10-26', 'ends_on' => '2026-10-26'])->assertSessionHasErrors('starts_on');
        $this->post(route('admin.leave.store'), ['staff_id' => Staff::factory()->create()->id, 'starts_on' => '2026-10-29', 'ends_on' => '2026-10-29'])->assertSessionHasErrors('staff_id');
    }
}
