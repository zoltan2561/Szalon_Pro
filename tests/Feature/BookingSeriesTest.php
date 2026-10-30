<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\Service;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\BookingSeries;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingSeriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19T06:00:00Z'));
        Salon::factory()->create(['horizon_days' => 365]);
    }

    public function test_repeated_visits_reuse_one_guest_and_preserve_wall_time_across_daylight_saving(): void
    {
        $service = Service::factory()->create();
        $data = $this->data($service);
        $first = app(BookingService::class)->create($data);
        $visits = Booking::orderBy('starts_at')->get();
        $this->assertCount(3, $visits);
        $this->assertDatabaseCount('guests', 1);
        $this->assertSame(1, $visits->pluck('series_key')->unique()->count());
        $this->assertSame(['2026-10-19 10:00', '2026-10-26 10:00', '2026-11-02 10:00'], $visits->map(fn ($visit) => $visit->starts_at->setTimezone('Europe/Budapest')->format('Y-m-d H:i'))->all());
        $this->assertSame($first->id, app(BookingService::class)->create($data)->id);
        $this->assertDatabaseCount('bookings', 3);
        $this->assertSame(3, OutboxMessage::where('kind', 'confirmation')->count());
        $this->assertDatabaseCount('outbox_messages', 8);
        $this->assertSame(3, OutboxMessage::where('kind', 'new_booking')->count());
    }

    public function test_a_conflict_rolls_back_every_visit_guest_and_notification(): void
    {
        $service = Service::factory()->create();
        TimeBlock::factory()->create(['staff_id' => $service->staff->first()->id, 'starts_at' => '2026-10-26 09:00:00', 'ends_at' => '2026-10-26 11:00:00']);
        try {
            app(BookingService::class)->create($this->data($service));
            $this->fail('A conflicting repeat booking must fail.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('repeat_count', $error->errors());
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('guests', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_preview_reports_conflicts_and_horizon_without_making_a_booking(): void
    {
        $service = Service::factory()->create();
        Salon::current()->update(['horizon_days' => 7]);
        $result = app(BookingSeries::class)->preview($service, $service->staff->first(), CarbonImmutable::parse('2026-10-19T10:00:00+02:00'), 1, 3);
        $this->assertTrue($result[0]['available']);
        $this->assertTrue($result[1]['available']);
        $this->assertFalse($result[2]['available']);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_following_cancellation_is_atomic_and_keeps_other_series_untouched(): void
    {
        $service = Service::factory()->create();
        $first = app(BookingService::class)->create($this->data($service));
        $other = Booking::factory()->create();
        app(BookingSeries::class)->changeFollowing($first, ['action' => 'cancel', 'revision' => 1], User::factory()->create(['role' => 'owner']));
        $this->assertSame(3, Booking::where('series_key', $first->series_key)->where('status', 'cancelled')->count());
        $this->assertSame('confirmed', $other->fresh()->status);
    }

    public function test_following_move_preserves_local_times_and_rolls_back_when_one_target_is_busy(): void
    {
        $service = Service::factory()->create();
        $first = app(BookingService::class)->create($this->data($service));
        $owner = User::factory()->create(['role' => 'owner']);
        $moved = app(BookingSeries::class)->changeFollowing($first, ['action' => 'move', 'revision' => 1, 'staff_id' => $first->staff_id, 'starts_at' => '2026-10-20T11:00:00+02:00'], $owner);
        $this->assertSame(['2026-10-20 11:00', '2026-10-27 11:00', '2026-11-03 11:00'], Booking::orderBy('starts_at')->get()->map(fn ($visit) => $visit->starts_at->setTimezone('Europe/Budapest')->format('Y-m-d H:i'))->all());
        TimeBlock::factory()->create(['staff_id' => $first->staff_id, 'starts_at' => '2026-10-28 10:00:00', 'ends_at' => '2026-10-28 12:00:00']);
        try {
            app(BookingSeries::class)->changeFollowing($moved, ['action' => 'move', 'revision' => 2, 'staff_id' => $first->staff_id, 'starts_at' => '2026-10-21T11:00:00+02:00'], $owner);
            $this->fail('All target appointments must be checked.');
        } catch (ValidationException) {
            $this->assertSame('2026-10-20 11:00', $first->fresh()->starts_at->setTimezone('Europe/Budapest')->format('Y-m-d H:i'));
        }
    }

    private function data(Service $service): array
    {
        return ['service_id' => $service->id, 'staff_id' => $service->staff->first()->id, 'starts_at' => '2026-10-19T10:00:00+02:00', 'name' => 'Rendszeres vendég', 'email' => 'repeat@example.test', 'request_key' => Str::uuid()->toString(), 'repeat_weeks' => 1, 'repeat_count' => 3];
    }
}
