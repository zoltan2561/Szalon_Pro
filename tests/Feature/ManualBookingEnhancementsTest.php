<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualBookingEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nearest_search_respects_staff_availability_and_manual_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 06:00:00', 'UTC'));
        Salon::factory()->create(['min_notice_minutes' => 0, 'horizon_days' => 7]);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $owner = User::factory()->create(['role' => 'owner']);
        TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => '2026-10-19 00:00:00', 'ends_at' => '2026-10-20 00:00:00']);

        $this->actingAs($owner)->get(route('admin.create', ['service_id' => $service->id, 'staff_id' => $staff->id, 'date' => '2026-10-23', 'nearest' => 1]))
            ->assertOk()->assertViewHas('date', '2026-10-20')->assertViewHas('slots', fn ($slots) => count($slots) > 0 && $slots[0]['staff_id'] === $staff->id);
        $this->actingAs($owner)->get(route('admin.create', ['service_id' => $service->id, 'staff_id' => $staff->id, 'date' => '2026-10-23']))
            ->assertOk()->assertViewHas('date', '2026-10-23');
    }

    public function test_guest_search_uses_the_existing_authorized_guest_list(): void
    {
        Salon::factory()->create();
        Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $guest = Guest::factory()->create(['name' => 'Kereshet? Vend?g']);
        $this->actingAs($owner)->get(route('admin.create', ['guest_id' => $guest->id]))
            ->assertOk()->assertSee('data-guest-search', false)->assertSee('Kereshet? Vend?g')->assertSee('selected', false);
    }

    public function test_manual_booking_shows_the_chosen_day_before_slots_and_guest_details_after_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 06:00:00', 'UTC'));
        Salon::factory()->create(['min_notice_minutes' => 0, 'horizon_days' => 7]);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => '2026-10-19 00:00:00', 'ends_at' => '2026-10-20 00:00:00']);
        $this->actingAs(User::factory()->create(['role' => 'owner']));

        foreach ([['date' => '2026-10-23'], ['date' => '2026-10-23', 'nearest' => 1]] as $choice) {
            $date = isset($choice['nearest']) ? '2026-10-20' : '2026-10-23';
            $response = $this->get(route('admin.create', $choice + ['service_id' => $service->id, 'staff_id' => $staff->id]))->assertOk();
            $response->assertSeeInOrder(['data-manual-slots', 'data-slot-date', 'datetime="'.$date.'"', 'name="slot"', 'data-manual-guest-fields', 'data-guest-search', 'data-manual-finalize'], false)
                ->assertSee(CarbonImmutable::parse($date, 'Europe/Budapest')->locale('hu')->isoFormat('YYYY. MMMM D., dddd'));
        }
    }

    public function test_calendar_prefills_the_selected_staff_and_exact_available_instant(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create(['min_notice_minutes' => 0]);
        Service::factory()->create();
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $owner = User::factory()->create(['role' => 'owner']);
        $params = ['date' => '2026-10-25', 'staff_id' => $staff->id, 'calendar_start' => '2026-10-25T09:00:00+00:00'];
        $selected = $staff->id.'|2026-10-25T10:00:00+01:00';

        $response = $this->actingAs($owner)->get(route('admin.create', $params));
        $response->assertOk()->assertViewHas('service', fn ($item) => $item->id === $service->id)
            ->assertViewHas('staffId', $staff->id)->assertViewHas('selectedSlot', $selected)
            ->assertSee('value="'.$selected.'" required checked', false);

        TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => '2026-10-25 09:00:00', 'ends_at' => '2026-10-25 10:00:00']);
        $this->get(route('admin.create', $params))->assertOk()->assertViewHas('selectedSlot', null)->assertSee('már nem foglalható');
        $this->from(route('admin.create', $params))->post(route('admin.store'), ['service_id' => $service->id, 'slot' => $selected, 'name' => 'Teszt vendég', 'request_key' => Str::uuid()->toString()])
            ->assertSessionHasErrors();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_calendar_shortcut_respects_staff_scope_and_matching_local_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create();
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $other = Staff::factory()->create();
        $user = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id, 'can_manage_bookings' => true]);
        $params = ['date' => '2026-10-25', 'staff_id' => $other->id, 'calendar_start' => '2026-10-25T09:00:00+00:00'];
        $this->actingAs($user)->get(route('admin.create', $params))->assertForbidden();
        $params['staff_id'] = $staff->id;
        $params['date'] = '2026-10-24';
        $this->get(route('admin.create', $params))->assertStatus(422);

        $this->get(route('admin.calendar', ['date' => '2026-10-25']))->assertOk()->assertSee('data-calendar-create=', false);
        $user->update(['can_manage_bookings' => false]);
        $this->get(route('admin.calendar', ['date' => '2026-10-25']))->assertOk()->assertDontSee('data-calendar-create=', false);
    }

    public function test_calendar_shortcut_distinguishes_repeated_times_during_clock_change(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        $hours = array_fill(1, 7, [['00:00', '04:00']]);
        Salon::factory()->create(['opening_hours' => $hours, 'min_notice_minutes' => 0]);
        $service = Service::factory()->create(['duration_minutes' => 30, 'buffer_before' => 0, 'buffer_after' => 0]);
        $staff = $service->staff()->firstOrFail();
        $staff->update(['weekly_hours' => $hours]);
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $params = ['date' => '2026-10-25', 'staff_id' => $staff->id];

        $this->get(route('admin.create', $params + ['calendar_start' => '2026-10-25T00:00:00+00:00']))
            ->assertOk()->assertViewHas('selectedSlot', $staff->id.'|2026-10-25T02:00:00+02:00');
        $this->get(route('admin.create', $params + ['calendar_start' => '2026-10-25T01:00:00+00:00']))
            ->assertOk()->assertViewHas('selectedSlot', $staff->id.'|2026-10-25T02:00:00+01:00');
    }
}
