<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\TimeBlock;
use App\Models\User;
use App\Models\WorkException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCalendarVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_marks_both_repeated_hours_and_positions_events_by_elapsed_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25T01:30:00Z'));
        Salon::factory()->create(['timezone' => 'Europe/Budapest']);
        $owner = User::factory()->create(['role' => 'owner']);
        $staff = Staff::factory()->create();
        TimeBlock::factory()->create([
            'staff_id' => $staff->id,
            'starts_at' => '2026-10-24 22:00:00',
            'ends_at' => '2026-10-25 23:00:00',
            'reason' => 'Egész nap',
        ]);
        $firstOccurrence = CarbonImmutable::parse('2026-10-25T00:15:00Z');
        Booking::factory()->create([
            'staff_id' => $staff->id,
            'starts_at' => $firstOccurrence,
            'ends_at' => $firstOccurrence->addMinutes(30),
            'busy_from' => $firstOccurrence,
            'busy_until' => $firstOccurrence->addMinutes(30),
        ]);
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $staff->id]);
        GoogleCalendarBusy::factory()->create([
            'google_calendar_connection_id' => $connection->id,
            'starts_at' => '2026-10-25 01:15:00',
            'ends_at' => '2026-10-25 01:45:00',
        ]);

        $this->actingAs($owner)->get(route('admin.calendar', ['date' => '2026-10-25']))
            ->assertOk()
            ->assertSee('height: 2250px', false)
            ->assertSee('top: 180px">02:00<small style="display: block; font-size: 9px">UTC+02:00', false)
            ->assertSee('top: 270px">02:00<small style="display: block; font-size: 9px">UTC+01:00', false)
            ->assertSee('top: 450px">04:00', false)
            ->assertSee('style="top: 202.5px; height: 45px"', false)
            ->assertSee('style="top: 292.5px; height: 45px" title="Google Naptár foglaltság"', false)
            ->assertSee('calendar-now-line" style="top: 315px"', false);
    }

    public function test_calendar_skips_the_missing_spring_hour_and_positions_events_by_elapsed_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-29T01:15:00Z'));
        Salon::factory()->create(['timezone' => 'Europe/Budapest']);
        $owner = User::factory()->create(['role' => 'owner']);
        $staff = Staff::factory()->create();
        TimeBlock::factory()->create([
            'staff_id' => $staff->id,
            'starts_at' => '2026-03-28 23:00:00',
            'ends_at' => '2026-03-29 22:00:00',
            'reason' => 'Egész nap',
        ]);
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $staff->id]);
        GoogleCalendarBusy::factory()->create([
            'google_calendar_connection_id' => $connection->id,
            'starts_at' => '2026-03-29 01:30:00',
            'ends_at' => '2026-03-29 02:00:00',
        ]);

        $this->actingAs($owner)->get(route('admin.calendar', ['date' => '2026-03-29']))
            ->assertOk()
            ->assertSee('height: 2070px', false)
            ->assertSee('top: 90px">01:00', false)
            ->assertSee('top: 180px">03:00', false)
            ->assertSee('top: 270px">04:00', false)
            ->assertDontSee('>02:00</span>', false)
            ->assertSee('style="top: 225px; height: 45px" title="Google Naptár foglaltság"', false)
            ->assertSee('calendar-now-line" style="top: 202.5px"', false);
    }

    public function test_calendar_clips_unavailable_intervals_to_the_displayed_day(): void
    {
        Salon::factory()->create(['timezone' => 'Europe/Budapest']);
        $owner = User::factory()->create(['role' => 'owner']);
        $staff = Staff::factory()->create();
        $midnight = CarbonImmutable::parse('2026-10-23 00:00', 'Europe/Budapest');
        TimeBlock::factory()->create([
            'staff_id' => $staff->id,
            'starts_at' => $midnight->subMinutes(30)->utc(),
            'ends_at' => $midnight->addMinutes(30)->utc(),
            'reason' => 'Éjszakai szünet',
        ]);
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $staff->id]);
        GoogleCalendarBusy::factory()->create([
            'google_calendar_connection_id' => $connection->id,
            'starts_at' => $midnight->subHour()->utc(),
            'ends_at' => $midnight->addMinutes(30)->utc(),
        ]);
        TimeBlock::factory()->create([
            'staff_id' => $staff->id,
            'starts_at' => $midnight->addHours(23)->utc(),
            'ends_at' => $midnight->addDay()->utc(),
            'reason' => 'Zárás',
        ]);

        $this->actingAs($owner)->get(route('admin.calendar', ['date' => '2026-10-23']))
            ->assertOk()
            ->assertSee('style="top: 0px; height: 45px" title="Éjszakai szünet"', false)
            ->assertSee('style="top: 0px; height: 45px" title="Google Naptár foglaltság"', false)
            ->assertSee('style="top: 2070px; height: 90px" title="Zárás"', false);

        $this->get(route('admin.calendar', ['date' => '2026-10-23', 'view' => 'week']))
            ->assertOk()
            ->assertSee('00:00 · Éjszakai szünet')
            ->assertSee('00:00 · Google-foglaltság');
    }

    public function test_calendar_shows_blocks_and_closed_days_without_exposing_another_staff_members_schedule(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23T06:00:00+00:00'));
        Salon::factory()->create();
        $staff = Staff::factory()->create(['name' => 'Hajnal Luca']);
        $otherStaff = Staff::factory()->create(['name' => 'Bíró Réka']);
        $owner = User::factory()->create(['role' => 'owner']);
        $staffUser = User::factory()->create(['role' => 'staff', 'staff_id' => $otherStaff->id]);
        $startsAt = CarbonImmutable::parse('2026-10-23 10:00', 'Europe/Budapest')->utc();
        Booking::factory()->create(['staff_id' => $staff->id, 'starts_at' => $startsAt, 'ends_at' => $startsAt->addHour(), 'buffer_before' => 15, 'busy_from' => $startsAt->subMinutes(15), 'busy_until' => $startsAt->addMinutes(75)]);
        TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => $startsAt->addMinutes(65), 'ends_at' => $startsAt->addMinutes(70), 'reason' => 'Ebédszünet']);
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $staff->id]);
        GoogleCalendarBusy::factory()->create(['google_calendar_connection_id' => $connection->id, 'starts_at' => $startsAt->subMinutes(10), 'ends_at' => $startsAt->subMinutes(5)]);
        WorkException::factory()->create(['staff_id' => $staff->id, 'date' => '2026-10-24', 'hours' => []]);

        $dayResponse = $this->actingAs($owner)->get(route('admin.calendar', ['date' => '2026-10-23']))
            ->assertOk()
            ->assertSee('Ebédszünet')
            ->assertSee('Google Naptár foglaltság')
            ->assertSee('Átfedő foglalás');
        $this->assertSame(2, substr_count($dayResponse->getContent(), 'Átfedő foglalás'));

        $this->get(route('admin.calendar', ['date' => '2026-10-23', 'view' => 'week']))
            ->assertOk()
            ->assertSee('Hajnal Luca')
            ->assertSee('2026-10-24')
            ->assertSee('Zárva');

        $this->actingAs($staffUser)->get(route('admin.calendar', ['date' => '2026-10-23', 'view' => 'week']))
            ->assertOk()
            ->assertDontSee('Ebédszünet')
            ->assertDontSee('Google Naptár foglaltság')
            ->assertDontSee('Hajnal Luca');
    }
}
