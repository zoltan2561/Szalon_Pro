<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
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
}
