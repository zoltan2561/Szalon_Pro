<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GiftVoucher;
use App\Models\Salon;
use App\Models\User;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GiftVouchersTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_redemption_and_reversal_are_atomic_and_idempotent(): void
    {
        Salon::factory()->create(['features' => ['vouchers' => true]]);
        $owner = User::factory()->create(['role' => 'owner']);
        $vouchers = app(VoucherService::class);
        $voucher = $vouchers->issue(['buyer_name' => 'Minta', 'amount' => 10000, 'expires_at' => now()->addYear()->toDateTimeString(), 'request_key' => Str::uuid()->toString()], $owner);
        $booking = Booking::factory()->create(['status' => 'completed']);
        $key = Str::uuid()->toString();
        $redemption = $vouchers->redeem($voucher, $booking, 3000, $key, $owner);
        $this->assertSame($redemption->id, $vouchers->redeem($voucher, $booking, 3000, $key, $owner)->id);
        $this->assertSame(7000, $voucher->fresh()->balance_amount);
        $this->assertDatabaseCount('voucher_redemptions', 1);
        $vouchers->reverse($redemption, 'Téves beváltás', $owner);
        $vouchers->reverse($redemption, 'Ismételt beküldés', $owner);
        $this->assertSame(10000, $voucher->fresh()->balance_amount);
        $this->assertSame('Téves beváltás', $redemption->fresh()->reversal_reason);
        $this->actingAs($owner)->get(route('admin.vouchers.index'))->assertOk()->assertSee($voucher->code);
        $this->get(route('vouchers.show', $voucher->public_token))->assertOk()->assertSee('Ajándékutalvány')->assertDontSee('Minta Vásárló');
    }

    public function test_expired_voucher_cannot_be_used_and_balance_is_unchanged(): void
    {
        Salon::factory()->create(['features' => ['vouchers' => true]]);
        $voucher = GiftVoucher::factory()->create(['expires_at' => now()->subDay()]);
        $booking = Booking::factory()->create(['status' => 'completed']);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->post(route('admin.vouchers.redeem', $booking), ['code' => $voucher->code, 'amount' => 1000, 'request_key' => Str::uuid()->toString()])->assertSessionHasErrors('voucher');
        $this->assertSame(10000, $voucher->fresh()->balance_amount);
        $this->assertDatabaseCount('voucher_redemptions', 0);
    }

    public function test_overspend_and_reusing_key_for_other_booking_are_rejected(): void
    {
        Salon::factory()->create(['features' => ['vouchers' => true]]);
        $voucher = GiftVoucher::factory()->create();
        $booking = Booking::factory()->create(['status' => 'completed']);
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->post(route('admin.vouchers.redeem', $booking), ['code' => $voucher->code, 'amount' => 10001, 'request_key' => Str::uuid()->toString()])->assertSessionHasErrors('voucher');
        $key = Str::uuid()->toString();
        app(VoucherService::class)->redeem($voucher, $booking, 1000, $key, $owner);
        $this->expectException(ValidationException::class);
        app(VoucherService::class)->redeem($voucher, Booking::factory()->create(['status' => 'completed']), 1000, $key, $owner);
    }
}
