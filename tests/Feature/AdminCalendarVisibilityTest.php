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
