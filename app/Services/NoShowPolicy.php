<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Builder;

class NoShowPolicy
{
    /** @param array<string, mixed> $data */
    public function blocks(array $data, ?Guest $knownGuest = null): bool
    {
        $salon = Salon::current();
        if (! $salon->featureEnabled('no_show_protection') || $salon->no_show_limit < 1) {
            return false;
        }
        $email = mb_strtolower(trim((string) ($knownGuest?->email ?? $data['email'] ?? '')));
        $phone = app(SmsGateway::class)->normalizeNumber($knownGuest?->phone ?? $data['phone'] ?? null);
        if ($email === '' && ! $phone && ! $knownGuest) {
            return false;
        }
        $count = Booking::where('status', 'no_show')->whereHas('guest', function (Builder $query) use ($email, $phone, $knownGuest): void {
            $query->whereNull('anonymized_at')->where(function (Builder $identity) use ($email, $phone, $knownGuest): void {
                $identity->whereRaw('1 = 0');
                if ($knownGuest) {
                    $identity->orWhereKey($knownGuest->id);
                }
                if ($email !== '') {
                    $identity->orWhereRaw('lower(email) = ?', [$email]);
                }
                if ($phone) {
                    $identity->orWhere('phone_normalized', $phone);
                }
            });
        })->count();

        return $count >= $salon->no_show_limit;
    }
}
