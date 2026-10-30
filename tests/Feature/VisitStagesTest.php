<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitStagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_arrival_and_start_keep_availability_reserved_and_pending_messages_current(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19T08:00:00Z'));
        Salon::factory()->create();
        $booking = Booking::factory()->create(['starts_at' => '2026-10-19 09:00:00', 'ends_at' => '2026-10-19 10:00:00']);
        OutboxMessage::factory()->create(['booking_id' => $booking->id, 'revision' => 1, 'kind' => 'reminder', 'status' => 'pending']);
        $this->actingAs(User::factory()->create(['role' => 'owner']))->post(route('admin.booking.update', $booking), ['action' => 'arrive', 'revision' => 1])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('Megérkezett', $booking->visitLabel());
        $this->assertDatabaseHas('outbox_messages', ['booking_id' => $booking->id, 'revision' => 2, 'status' => 'pending']);
        $this->post(route('admin.booking.update', $booking), ['action' => 'start', 'revision' => 2])->assertSessionHasNoErrors();
        $this->assertSame('Folyamatban', $booking->fresh()->visitLabel());
        $booking->service->staff()->syncWithoutDetaching([$booking->staff_id]);
        $this->post(route('admin.booking.update', $booking), ['action' => 'move', 'revision' => 3, 'slot' => $booking->staff_id.'|2026-10-20T10:00:00+02:00'])->assertSessionHasNoErrors();
        $this->assertSame('Visszaigazolt', $booking->fresh()->visitLabel());
        $this->assertNull($booking->fresh()->arrived_at);
        $this->assertNull($booking->fresh()->started_at);
    }

    public function test_arrival_cannot_be_recorded_for_another_staff_member_or_another_day(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'staff', 'can_manage_bookings' => true]))->post(route('admin.booking.update', $booking), ['action' => 'arrive', 'revision' => 1])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'owner']))->post(route('admin.booking.update', $booking), ['action' => 'arrive', 'revision' => 1])->assertSessionHasErrors('starts_at');
    }

    public function test_daily_review_shows_only_the_visible_automatically_completed_visits_and_clears_after_confirmation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19T12:00:00Z'));
        Salon::factory()->create();
        $booking = Booking::factory()->create(['starts_at' => '2026-10-19 08:00:00', 'ends_at' => '2026-10-19 09:00:00']);
        $this->assertTrue(app(BookingService::class)->completeExpiredBooking($booking->id, CarbonImmutable::now()->subHour()));
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.today'))->assertOk()->assertSee('Napi ellenőrzés · 1');
        $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_id' => Staff::factory()->create()->id]))->get(route('admin.today'))->assertOk()->assertDontSee('Napi ellenőrzés');
        $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id, 'can_manage_bookings' => true]))
            ->post(route('admin.booking.update', $booking), ['action' => 'complete', 'revision' => 2])->assertSessionHasNoErrors();
        $this->get(route('admin.today'))->assertOk()->assertDontSee('Napi ellenőrzés');
    }
}
