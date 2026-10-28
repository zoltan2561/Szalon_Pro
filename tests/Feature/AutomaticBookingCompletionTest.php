<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\SmsOutboxMessage;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticBookingCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_completes_only_confirmed_bookings_one_hour_after_their_end(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00:00 UTC'));
        Salon::factory()->create(['features' => ['financials' => true, 'loyalty' => true, 'passes' => true, 'reviews' => true]]);
        $due = Booking::factory()->create(['ends_at' => now()->subHour(), 'actual_amount' => null]);
        $notYetDue = Booking::factory()->create(['ends_at' => now()->subHour()->addSecond()]);
        $cancelled = Booking::factory()->create(['status' => 'cancelled', 'ends_at' => now()->subHours(2)]);
        $noShow = Booking::factory()->create(['status' => 'no_show', 'ends_at' => now()->subHours(2)]);
        $completed = Booking::factory()->create(['status' => 'completed', 'ends_at' => now()->subHours(2)]);
        $email = OutboxMessage::factory()->create(['booking_id' => $due->id]);
        $sms = SmsOutboxMessage::create(['booking_id' => $due->id, 'revision' => 1, 'kind' => 'reminder', 'scheduled_at' => now()]);

        $this->assertTrue(collect(app(Schedule::class)->events())->contains(fn ($event) => str_contains($event->command ?? '', 'salon:complete-expired') && $event->expression === '* * * * *'));
        $this->artisan('salon:complete-expired')->expectsOutputToContain('1 foglalás')->assertSuccessful();
        $this->assertSame('completed', $due->fresh()->status);
        $this->assertSame(2, $due->fresh()->revision);
        $this->assertNull($due->fresh()->actual_amount);
        $event = $due->events()->sole();
        $this->assertSame('complete', $event->action);
        $this->assertNull($event->actor_id);
        $this->assertTrue($event->changes['automatic']);
        $this->assertSame('obsolete', $email->fresh()->status);
        $this->assertSame('obsolete', $sms->fresh()->status);
        $this->assertDatabaseCount('loyalty_entries', 0);
        $this->assertDatabaseCount('pass_uses', 0);
        $this->assertDatabaseCount('fiscal_documents', 0);
        $this->assertDatabaseMissing('outbox_messages', ['booking_id' => $due->id, 'kind' => 'review']);
        $this->assertSame('confirmed', $notYetDue->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('no_show', $noShow->fresh()->status);
        $this->assertSame('completed', $completed->fresh()->status);
        $this->artisan('salon:complete-expired')->expectsOutputToContain('0 foglalás')->assertSuccessful();
        $this->assertSame(1, $due->events()->count());
    }

    public function test_expiry_is_rechecked_under_the_booking_lock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00:00 UTC'));
        Salon::factory()->create();
        $moved = Booking::factory()->create(['ends_at' => now()->subHours(2)]);
        $cancelled = Booking::factory()->create(['ends_at' => now()->subHours(2)]);
        $moved->update(['ends_at' => now()->addHour(), 'revision' => 2]);
        $cancelled->update(['status' => 'cancelled', 'revision' => 2]);
        $service = app(BookingService::class);

        $this->assertFalse($service->completeExpiredBooking($moved->id, CarbonImmutable::now()->subHour()));
        $this->assertFalse($service->completeExpiredBooking($cancelled->id, CarbonImmutable::now()->subHour()));
        $this->assertSame('confirmed', $moved->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertDatabaseCount('booking_events', 0);
    }
}
