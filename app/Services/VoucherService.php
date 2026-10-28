<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GiftVoucher;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Models\VoucherRedemption;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    /** @param array{buyer_name: string, amount?: int|null, service_id?: int|null, expires_at: string, request_key: string} $data */
    public function issue(array $data, User $actor): GiftVoucher
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($data, $actor): GiftVoucher {
            Salon::whereKey(1)->increment('lock_version');
            if ($existing = GiftVoucher::where('issue_key', $data['request_key'])->first()) {
                if ($existing->buyer_name !== $data['buyer_name'] || $existing->service_id !== ($data['service_id'] ?? null)
                    || (isset($data['amount']) && $existing->initial_amount !== (int) $data['amount'])
                    || ! $existing->expires_at->equalTo(CarbonImmutable::parse($data['expires_at']))) {
                    $this->error('A kiadási azonosító másik utalványhoz tartozik.');
                }

                return $existing;
            }
            $service = ! empty($data['service_id']) ? Service::where('active', true)->findOrFail($data['service_id']) : null;
            $amount = $data['amount'] ?? $service?->price;
            if (! $amount || $amount < 1) {
                $this->error('Pozitív utalványösszeg szükséges.');
            }
            $token = bin2hex(random_bytes(32));

            return GiftVoucher::create(['code' => strtoupper(bin2hex(random_bytes(8))), 'public_token' => $token, 'public_token_hash' => hash('sha256', $token), 'buyer_name' => $data['buyer_name'], 'service_id' => $service?->id, 'service_name' => $service?->name, 'initial_amount' => $amount, 'balance_amount' => $amount, 'expires_at' => $data['expires_at'], 'issued_by_user_id' => $actor->id, 'issue_key' => $data['request_key']]);
        }, 5);
    }

    public function redeem(GiftVoucher $voucher, Booking $booking, int $amount, string $requestKey, User $actor): VoucherRedemption
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($voucher, $booking, $amount, $requestKey, $actor): VoucherRedemption {
            Salon::whereKey(1)->increment('lock_version');
            if ($existing = VoucherRedemption::where('request_key', $requestKey)->first()) {
                if ($existing->gift_voucher_id !== $voucher->id || $existing->booking_id !== $booking->id || $existing->amount !== $amount) {
                    $this->error('Ez a beváltási azonosító másik művelethez tartozik.');
                }

                return $existing;
            }
            $voucher = GiftVoucher::lockForUpdate()->findOrFail($voucher->id);
            $booking = Booking::findOrFail($booking->id);
            if ($booking->isAutomaticallyCompleted()) {
                $this->error('Utalvány beváltása előtt pontosítsd az automatikusan lezárt látogatást.');
            }
            $used = VoucherRedemption::where('booking_id', $booking->id)->whereNull('reversed_at')->sum('amount');
            if ($booking->status !== 'completed' || $voucher->voided_at || $voucher->expires_at->lte(now()) || $amount < 1
                || ($voucher->service_id && $voucher->service_id !== $booking->service_id)
                || $used + $amount > ($booking->actual_amount ?? $booking->price) || $amount > $voucher->balance_amount) {
                $this->error('Az utalvány, az összeg vagy a foglalás nem alkalmas erre a beváltásra.');
            }
            $voucher->decrement('balance_amount', $amount);

            return VoucherRedemption::create(['gift_voucher_id' => $voucher->id, 'booking_id' => $booking->id, 'amount' => $amount, 'request_key' => $requestKey, 'created_by_user_id' => $actor->id]);
        }, 5);
    }

    public function reverse(VoucherRedemption $redemption, string $reason, User $actor): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($redemption, $reason, $actor): void {
            Salon::whereKey(1)->increment('lock_version');
            $redemption = VoucherRedemption::lockForUpdate()->findOrFail($redemption->id);
            if ($redemption->reversed_at) {
                return;
            }
            $redemption->update(['reversed_at' => now(), 'reversed_by_user_id' => $actor->id, 'reversal_reason' => $reason]);
            $redemption->voucher()->increment('balance_amount', $redemption->amount);
        }, 5);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->role === 'owner' && Salon::current()->featureEnabled('vouchers'), 403);
    }

    private function error(string $message): never
    {
        throw ValidationException::withMessages(['voucher' => $message]);
    }
}
