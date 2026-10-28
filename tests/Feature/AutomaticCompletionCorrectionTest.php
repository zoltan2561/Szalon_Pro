<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GiftVoucher;
use App\Models\GuestPass;
use App\Models\PassPlan;
use App\Models\Salon;
use App\Models\TimeBlock;
use App\Models\User;
use App\Services\Availability;
use App\Services\BillingService;
use App\Services\BookingService;
use App\Services\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutomaticCompletionCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function automaticBooking(): Booking
    {
        $booking = Booking::factory()->create([
            'starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2),
            'busy_from' => now()->subHours(3), 'busy_until' => now()->subHours(2),
        ]);
        $this->assertTrue(app(BookingService::class)->completeExpiredBooking($booking->id, now()->subHour()->toImmutable()));

        $booking = $booking->fresh();
        $this->assertSame(true, $booking->events()->latest('id')->first()->changes['automatic'] ?? null);
        $this->assertSame('complete', $booking->events()->latest('id')->first()->action);
        $this->assertSame('completed', $booking->status);
        $this->assertTrue($booking->isAutomaticallyCompleted());

        return $booking;
    }

    public function test_automatic_completion_can_be_confirmed_once_with_actual_amount(): void
    {
        Salon::factory()->create(['features' => ['financials' => true, 'loyalty' => true]]);
        $owner = User::factory()->create(['role' => 'owner']);
        $booking = $this->automaticBooking();
        $this->actingAs($owner)->get(route('admin.booking', $booking))->assertOk()
            ->assertSee('Automatikusan lezárt látogatás')->assertSee('Rendszer')->assertSee('Látogatás pontosítása');

        $payload = ['action' => 'complete', 'revision' => $booking->revision, 'actual_amount' => 12345];
        $this->post(route('admin.booking.update', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertSame(12345, $booking->fresh()->actual_amount);
        $this->assertFalse($booking->fresh()->isAutomaticallyCompleted());
        $this->assertDatabaseCount('loyalty_entries', 1);
        $this->post(route('admin.booking.update', $booking), $payload)->assertSessionHasErrors();
        $this->assertDatabaseCount('loyalty_entries', 1);
    }

    public function test_automatic_completion_can_be_corrected_to_no_show_only_by_authorized_staff(): void
    {
        Salon::factory()->create();
        $booking = $this->automaticBooking();
        $other = User::factory()->create(['role' => 'staff', 'can_manage_bookings' => true]);
        $this->actingAs($other)->post(route('admin.booking.update', $booking), ['action' => 'no_show', 'revision' => $booking->revision])->assertForbidden();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->post(route('admin.booking.update', $booking), ['action' => 'no_show', 'revision' => $booking->revision])->assertSessionHasNoErrors();
        $this->assertSame('no_show', $booking->fresh()->status);
        $this->assertDatabaseCount('loyalty_entries', 0);
    }

    public function test_manual_confirmation_can_apply_a_pass_once(): void
    {
        Salon::factory()->create(['features' => ['passes' => true]]);
        $owner = User::factory()->create(['role' => 'owner']);
        $booking = $this->automaticBooking();
        $plan = PassPlan::factory()->create(['service_id' => $booking->service_id]);
        $pass = GuestPass::factory()->create(['pass_plan_id' => $plan->id, 'guest_id' => $booking->guest_id]);
        $payload = ['action' => 'complete', 'revision' => $booking->revision, 'guest_pass_id' => $pass->id];
        $this->actingAs($owner)->post(route('admin.booking.update', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertSame(4, $pass->fresh()->visits_remaining);
        $this->post(route('admin.booking.update', $booking), $payload)->assertSessionHasErrors();
        $this->assertSame(4, $pass->fresh()->visits_remaining);
    }

    public function test_automatic_completion_can_be_cancelled_or_moved_with_valid_availability(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $cancelled = $this->automaticBooking();
        $moved = $this->automaticBooking();
        $this->actingAs($owner)->post(route('admin.booking.update', $cancelled), ['action' => 'cancel', 'revision' => $cancelled->revision])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertDatabaseHas('outbox_messages', ['booking_id' => $cancelled->id, 'kind' => 'cancelled']);

        $staff = $moved->service->staff->first();
        $date = now(Salon::current()->timezone)->addDay()->toDateString();
        $slots = app(Availability::class)->slots($moved->service, $date, $staff->id, $moved);
        $this->assertNotEmpty($slots);
        $slot = $slots[0];
        $block = TimeBlock::factory()->create(['staff_id' => $staff->id, 'starts_at' => $slot['starts_at'], 'ends_at' => CarbonImmutable::parse($slot['starts_at'])->addHours(2)]);
        $this->post(route('admin.booking.update', $moved), ['action' => 'move', 'revision' => $moved->revision, 'slot' => $staff->id.'|'.$slot['starts_at']])->assertSessionHasErrors();
        $this->assertSame('completed', $moved->fresh()->status);
        $block->delete();
        $this->post(route('admin.booking.update', $moved), ['action' => 'move', 'revision' => $moved->revision, 'slot' => $staff->id.'|'.$slot['starts_at']])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $moved->fresh()->status);
        $this->assertSame($date, $moved->fresh()->starts_at->setTimezone(Salon::current()->timezone)->toDateString());
        $this->assertDatabaseHas('outbox_messages', ['booking_id' => $moved->id, 'kind' => 'changed']);
    }

    public function test_financial_operations_require_manual_confirmation(): void
    {
        Salon::factory()->create(['features' => ['billing' => true, 'vouchers' => true], 'billing_agent_key' => 'test', 'billing_vat' => 'AAM', 'billing_receipt_prefix' => 'NY', 'billing_sending_enabled' => true, 'billing_approved_at' => now()]);
        $owner = User::factory()->create(['role' => 'owner']);
        $booking = $this->automaticBooking();
        $voucher = GiftVoucher::factory()->create();
        Http::fake();
        $this->actingAs($owner)->get(route('admin.booking', $booking))->assertOk()->assertSee('Bizonylat kiállításához vagy utalvány beváltásához előbb pontosítsd');

        try {
            app(BillingService::class)->issue($booking, ['kind' => 'receipt'], $owner);
            $this->fail('Automatic completion must not issue a document.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('fiscal_documents', 0);
        }
        Http::assertNothingSent();
        try {
            app(VoucherService::class)->redeem($voucher, $booking, 1000, 'automatic-test', $owner);
            $this->fail('Automatic completion must not redeem a voucher.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('voucher_redemptions', 0);
        }
        $this->assertSame($voucher->balance_amount, $voucher->fresh()->balance_amount);
    }
}
