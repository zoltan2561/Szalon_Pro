<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_staff_timeline_and_readable_daily_and_weekly_booking_cards(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23T06:00:00+00:00'));
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $staff = Staff::factory()->create(['name' => 'Kiss Anna']);
        $guest = Guest::factory()->create(['name' => 'Nagy Eszter']);
        $otherStaff = Staff::factory()->create(['name' => 'Bíró Réka']);
        $otherGuest = Guest::factory()->create(['name' => 'Tóth Edit']);
        $staffUser = User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]);
        $startsAt = CarbonImmutable::parse('2026-10-23 10:00', 'Europe/Budapest')->utc();
        Booking::factory()->create([
            'staff_id' => $staff->id,
            'guest_id' => $guest->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'busy_from' => $startsAt,
            'busy_until' => $startsAt->addMinutes(75),
        ]);
        Booking::factory()->create([
            'staff_id' => $otherStaff->id,
            'guest_id' => $otherGuest->id,
            'starts_at' => $startsAt->addMinutes(30),
            'ends_at' => $startsAt->addMinutes(90),
            'busy_from' => $startsAt->addMinutes(30),
            'busy_until' => $startsAt->addMinutes(105),
        ]);

        $this->actingAs($owner)->get(route('admin.calendar', ['date' => '2026-10-23']))
            ->assertOk()
            ->assertSee('calendar-timeline-event confirmed', false)
            ->assertSee('calendar-agenda-item confirmed', false)
            ->assertSee('Kiss Anna')
            ->assertSee('Nagy Eszter')
            ->assertSee('tabindex="0" aria-label="Idővonal"', false)
            ->assertSee('Napi naptár');

        $this->get(route('admin.calendar', ['date' => '2026-10-23', 'view' => 'week']))
            ->assertOk()
            ->assertSee('calendar-week-grid', false)
            ->assertSee('calendar-week-booking confirmed', false)
            ->assertSee('2026. 10. 19. – 2026. 10. 25.')
            ->assertSee('Nagy Eszter')
            ->assertSee('Kiss Anna');

        $this->actingAs($staffUser)->get(route('admin.calendar', ['date' => '2026-10-23']))
            ->assertOk()
            ->assertSee('Kiss Anna')
            ->assertSee('Nagy Eszter')
            ->assertDontSee('Bíró Réka')
            ->assertDontSee('Tóth Edit');
    }

    public function test_financial_summary_is_only_visible_when_its_module_is_enabled(): void
    {
        $salon = Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get(route('admin.today'))
            ->assertOk()
            ->assertDontSee('Elvégzett szolgáltatások összege');

        $salon->update(['features' => ['financials' => true]]);

        $this->get(route('admin.today'))
            ->assertOk()
            ->assertSee('Elvégzett szolgáltatások összege');
    }

    public function test_booking_header_names_its_referral_source_and_history_actor(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Kiss Anna']);
        $booking = Booking::factory()->create(['source' => 'online', 'referral_source' => 'instagram']);
        BookingEvent::create(['booking_id' => $booking->id, 'actor_id' => $owner->id, 'action' => 'created', 'changes' => []]);
        $this->actingAs($owner)->get(route('admin.booking', $booking))->assertOk()
            ->assertSee('Foglalás #'.$booking->id.' · Online · Instagram')
            ->assertSee('Kiss Anna')->assertDontSee('Munkatárs #'.$owner->id);
    }
}
