<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkException;
use App\Services\SalonStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalonStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_statistics_count_statuses_sources_and_working_minutes(): void
    {
        $salon = Salon::factory()->create(['features' => ['statistics' => true], 'opening_hours' => [1 => [['09:00', '17:00']]]]);
        $staff = Staff::factory()->create(['weekly_hours' => [1 => [['09:00', '12:00'], ['13:00', '17:00']]]]);
        $start = CarbonImmutable::parse('2026-10-19 09:00', 'Europe/Budapest')->utc();
        foreach (['confirmed', 'completed', 'cancelled', 'no_show'] as $index => $status) {
            Booking::factory()->create(['staff_id' => $staff->id, 'status' => $status, 'source' => $index % 2 ? 'manual' : 'online', 'referral_source' => 'instagram', 'starts_at' => $start->addHours($index), 'busy_from' => $start->addHours($index), 'busy_until' => $start->addHours($index + 1), 'actual_amount' => 1000]);
        }
        $from = CarbonImmutable::parse('2026-10-19', 'Europe/Budapest');
        $stats = app(SalonStatistics::class)->summarize($from, $from->addWeek());
        $this->assertSame(4, $stats['total']);
        $this->assertSame(25.0, $stats['statuses']['completed']['percent']);
        $this->assertSame(4, $stats['sources']['instagram']);
        $this->assertSame(420, collect($stats['utilization'])->firstWhere('id', $staff->id)['work_minutes']);
        $this->assertNull($stats['amount']);
        WorkException::factory()->create(['staff_id' => $staff->id, 'date' => '2026-10-19', 'hours' => []]);
        $closedStats = app(SalonStatistics::class)->summarize($from, $from->addWeek());
        $this->assertSame(0, collect($closedStats['utilization'])->firstWhere('id', $staff->id)['work_minutes']);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get(route('admin.statistics.index', ['period' => 'week', 'date' => '2026-10-19']))->assertOk()->assertSee('Összes foglalás: 4')->assertDontSee('Rögzített szolgáltatási összeg');
        $salon->update(['features' => ['statistics' => true, 'financials' => true]]);
        $this->get(route('admin.statistics.index', ['period' => 'month', 'date' => '2026-10-19']))->assertOk()->assertSee('Rögzített szolgáltatási összeg');

        $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_id' => $staff->id]))->get(route('admin.statistics.index'))->assertForbidden();
    }
}
