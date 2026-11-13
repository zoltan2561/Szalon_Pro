<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopulateDemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_customer_and_unpublished_installations(): void
    {
        $salon = Salon::factory()->create(['demo' => false]);
        Service::factory()->create();

        $this->artisan('salon:demo-data')->assertExitCode(Command::FAILURE);
        $salon->update(['demo' => true, 'published' => false]);
        $this->artisan('salon:demo-data')->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('guests', 0);
    }

    public function test_it_needs_an_active_service_with_an_active_staff_member(): void
    {
        Salon::factory()->create(['demo' => true]);

        $this->artisan('salon:demo-data')->assertExitCode(Command::FAILURE);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_it_creates_a_lively_calendar_without_overlaps_or_notifications(): void
    {
        $this->demoSalon();

        $this->artisan('salon:demo-data')->assertExitCode(Command::SUCCESS);

        $bookings = Booking::with('guest')->orderBy('starts_at')->get();
        $this->assertGreaterThan(60, $bookings->count());
        $this->assertEqualsCanonicalizing(['cancelled', 'completed', 'confirmed', 'no_show'], $bookings->pluck('status')->unique()->values()->all());
        $this->assertTrue($bookings->contains(fn (Booking $booking): bool => $booking->starts_at->isPast()));
        $this->assertTrue($bookings->contains(fn (Booking $booking): bool => $booking->starts_at->isFuture()));
        $this->assertTrue($bookings->every(fn (Booking $booking): bool => str_ends_with((string) $booking->guest->getRawOriginal('email'), '@example.test')));
        foreach ($bookings->groupBy('staff_id') as $staffBookings) {
            $previous = null;
            foreach ($staffBookings as $booking) {
                if ($previous) {
                    $this->assertTrue($booking->busy_from->greaterThanOrEqualTo($previous->busy_until), 'Bookings of one staff member must not overlap.');
                }
                $previous = $booking;
            }
        }
        foreach ($bookings as $booking) {
            $local = $booking->starts_at->setTimezone('Europe/Budapest');
            $this->assertGreaterThanOrEqual(9, $local->hour);
            $this->assertLessThanOrEqual('18:00', $booking->busy_until->setTimezone('Europe/Budapest')->format('H:i'));
            $this->assertSame($booking->status === 'completed' ? $booking->price : null, $booking->actual_amount);
        }
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->assertDatabaseCount('sms_outbox_messages', 0);
    }

    public function test_closed_days_receive_no_bookings(): void
    {
        $salon = $this->demoSalon();
        $hours = $salon->opening_hours;
        $hours[7] = [];
        $salon->update(['opening_hours' => $hours]);

        $this->artisan('salon:demo-data')->assertExitCode(Command::SUCCESS);

        $this->assertFalse(Booking::all()->contains(fn (Booking $booking): bool => $booking->starts_at->setTimezone('Europe/Budapest')->isoWeekday() === 7));
    }

    public function test_a_second_run_changes_nothing_and_refresh_only_replaces_its_own_data(): void
    {
        $this->demoSalon();
        $realGuest = Guest::factory()->create(['name' => 'Valódi Vendég', 'email' => 'real@example.test']);
        $realBooking = Booking::factory()->create(['guest_id' => $realGuest->id, 'status' => 'completed']);

        $this->artisan('salon:demo-data')->assertExitCode(Command::SUCCESS);
        $count = Booking::count();
        $signature = fn (): array => Booking::where('id', '!=', $realBooking->id)->orderBy('starts_at')->orderBy('staff_id')->get()
            ->map(fn (Booking $booking): array => [$booking->starts_at->timestamp, $booking->staff_id, $booking->service_id])->all();
        $before = $signature();

        $this->artisan('salon:demo-data')->assertExitCode(Command::SUCCESS);
        $this->assertSame($count, Booking::count());

        $this->artisan('salon:demo-data --refresh')->assertExitCode(Command::SUCCESS);
        $this->assertSame($count, Booking::count());
        $this->assertDatabaseHas('bookings', ['id' => $realBooking->id, 'guest_id' => $realGuest->id]);
        $this->assertDatabaseHas('guests', ['id' => $realGuest->id, 'name' => 'Valódi Vendég']);
        $this->assertSame(24, Guest::where('email', 'like', 'demo-minta-%@example.test')->count());
        $this->assertSame($before, $signature(), 'Refresh regenerates the same deterministic calendar.');
    }

    private function demoSalon(): Salon
    {
        $salon = Salon::factory()->create(['demo' => true]);
        $service = Service::factory()->create(['name' => 'Hajvágás', 'duration_minutes' => 45, 'buffer_after' => 15]);
        $second = Service::factory()->create(['name' => 'Festés', 'duration_minutes' => 90, 'price' => 18000, 'price_kind' => 'from']);
        $staff = Staff::factory()->create(['name' => 'Első Szakember']);
        $other = Staff::factory()->create(['name' => 'Második Szakember']);
        $service->staff()->sync([$staff->id, $other->id]);
        $second->staff()->sync([$staff->id, $other->id]);

        return $salon;
    }
}
