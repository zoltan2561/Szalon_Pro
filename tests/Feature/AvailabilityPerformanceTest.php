<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Services\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilityPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_slots_use_at_most_ten_queries_and_match_final_conflict_guard(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        Salon::factory()->create();
        $service = Service::factory()->create();
        $staff = $service->staff()->first();
        $connection = GoogleCalendarConnection::factory()->create(['staff_id' => $staff->id, 'sync_status' => 'connected', 'last_imported_at' => now()]);
        GoogleCalendarBusy::factory()->create(['google_calendar_connection_id' => $connection->id, 'starts_at' => '2026-10-19 07:30:00', 'ends_at' => '2026-10-19 08:30:00']);
        TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => '2026-10-19 10:00:00', 'ends_at' => '2026-10-19 11:00:00']);
        Booking::factory()->create(['staff_id' => $staff->id, 'service_id' => $service->id, 'busy_from' => '2026-10-19 12:00:00', 'busy_until' => '2026-10-19 13:00:00']);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $availability = app(Availability::class);
        $slots = $availability->slots($service, '2026-10-19', $staff->id);
        $this->assertLessThanOrEqual(10, $queries);
        $expected = [];
        $day = CarbonImmutable::parse('2026-10-19', 'Europe/Budapest');
        for ($time = $day->utc(); $time->lt($day->addDay()->utc()); $time = $time->addMinutes(15)) {
            if ($availability->canBook($service, $staff, $time)) {
                $expected[] = $time->setTimezone('Europe/Budapest')->format('Y-m-d\TH:i:sP');
            }
        }

        $this->assertNotEmpty($slots);
        $this->assertSame($expected, array_column($slots, 'starts_at'));
    }

    public function test_year_search_handles_many_bookings_with_short_gaps_and_matches_daily_slots(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16T06:00:00Z'));
        $hours = array_fill(1, 7, [['09:00', '18:00']]);
        Salon::factory()->create(['horizon_days' => 365, 'opening_hours' => $hours]);
        $service = Service::factory()->create();
        $staff = $service->staff()->firstOrFail();
        $staff->update(['weekly_hours' => $hours]);
        $guest = Guest::factory()->create();
        $startDay = CarbonImmutable::parse('2026-10-16', 'Europe/Budapest');
        $rows = [];
        for ($day = 0; $day <= 365; $day++) {
            for ($hour = 9; $hour < 18; $hour++) {
                $start = $startDay->addDays($day)->setTime($hour, 0)->utc();
                $rows[] = Booking::factory()->make(['guest_id' => $guest->id, 'service_id' => $service->id, 'staff_id' => $staff->id,
                    'starts_at' => $start, 'ends_at' => $start->addMinutes(50), 'busy_from' => $start, 'busy_until' => $start->addMinutes(50)])->getAttributes();
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            Booking::insert($chunk);
        }

        $availability = app(Availability::class);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->assertNull($availability->firstAvailableDay($service, $startDay, $startDay->addDays(365), $staff->id));
        $this->assertLessThanOrEqual(100, $queries);
        foreach (['2026-10-19', '2026-10-25', '2027-03-28'] as $date) {
            $day = CarbonImmutable::parse($date, 'Europe/Budapest');
            $slots = $availability->slots($service, $date, $staff->id);
            $expected = [];
            for ($time = $day->utc(); $time->lt($day->addDay()->utc()); $time = $time->addMinutes(15)) {
                if ($availability->canBook($service, $staff, $time)) {
                    $expected[] = $time->setTimezone('Europe/Budapest')->format('Y-m-d\TH:i:sP');
                }
            }
            $this->assertSame($expected, array_column($slots, 'starts_at'));
        }
    }
}
