<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescheduleSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_current_time_with_the_same_staff_member_is_not_offered_as_a_new_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'buffer_after' => 0]);
        $staff = $service->staff()->first();
        $start = CarbonImmutable::parse('2026-10-19 10:00', 'Europe/Budapest');
        $booking = Booking::factory()->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => $start->utc(), 'ends_at' => $start->addHour()->utc(), 'busy_from' => $start->utc(), 'busy_until' => $start->addHour()->utc()]);
        $availability = app(Availability::class);

        $all = collect($availability->slots($service, '2026-10-19', $staff->id, $booking));
        $moving = collect($availability->slotsForMoving($service, '2026-10-19', $staff->id, $booking));

        $this->assertTrue($all->contains(fn (array $slot): bool => $slot['label'] === '10:00'), 'The own booking frees its slot for the availability check.');
        $this->assertFalse($moving->contains(fn (array $slot): bool => $slot['label'] === '10:00'));
        $this->assertSame($all->count() - 1, $moving->count());
    }

    public function test_the_same_time_with_another_staff_member_is_still_offered(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'buffer_after' => 0]);
        $staff = $service->staff()->first();
        $other = Staff::factory()->create();
        $service->staff()->attach($other);
        $start = CarbonImmutable::parse('2026-10-19 10:00', 'Europe/Budapest');
        $booking = Booking::factory()->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => $start->utc(), 'ends_at' => $start->addHour()->utc(), 'busy_from' => $start->utc(), 'busy_until' => $start->addHour()->utc()]);

        $slots = collect(app(Availability::class)->slotsForMoving($service, '2026-10-19', null, $booking));

        $this->assertTrue($slots->contains(fn (array $slot): bool => $slot['label'] === '10:00' && $slot['staff_id'] === $other->id));
        $this->assertFalse($slots->contains(fn (array $slot): bool => $slot['label'] === '10:00' && $slot['staff_id'] === $staff->id));
    }

    public function test_guest_and_admin_move_forms_do_not_list_the_current_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create();
        $service = Service::factory()->create(['duration_minutes' => 60, 'buffer_after' => 0]);
        $staff = $service->staff()->first();
        $start = CarbonImmutable::parse('2026-10-19 10:00', 'Europe/Budapest');
        $booking = Booking::factory()->create(['service_id' => $service->id, 'staff_id' => $staff->id, 'starts_at' => $start->utc(), 'ends_at' => $start->addHour()->utc(), 'busy_from' => $start->utc(), 'busy_until' => $start->addHour()->utc()]);
        $currentSlotValue = $staff->id.'|2026-10-19T10:00:00+02:00';

        $this->get(route('booking.manage', ['token' => $booking->manage_token, 'date' => '2026-10-19']))->assertOk()
            ->assertSee($staff->id.'|2026-10-19T09:00:00+02:00', false)
            ->assertDontSee($currentSlotValue, false);
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.booking', ['booking' => $booking, 'date' => '2026-10-19']))->assertOk()
            ->assertSee($staff->id.'|2026-10-19T09:00:00+02:00', false)
            ->assertDontSee($currentSlotValue, false);
    }
}
