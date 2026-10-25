<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\SmsOutboxMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class ReviewRequests
{
    public function queue(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            Salon::whereKey(1)->increment('lock_version');
            $booking = Booking::findOrFail($booking->id);
            $guest = $booking->guest()->lockForUpdate()->firstOrFail();
            if (! $this->eligible($booking, $guest) || $this->contactGuests($guest)->where('last_review_requested_at', '>', now()->subDays(90))->exists()) {
                return;
            }
            $scheduled = now()->addHours(Salon::current()->review_delay_hours);
            if ($guest->email) {
                OutboxMessage::firstOrCreate(['booking_id' => $booking->id, 'revision' => $booking->revision, 'kind' => 'review'], ['scheduled_at' => $scheduled]);
            } elseif (Salon::current()->featureEnabled('sms') && app(SmsGateway::class)->configured() && $guest->phone_normalized) {
                SmsOutboxMessage::firstOrCreate(['booking_id' => $booking->id, 'revision' => $booking->revision, 'kind' => 'review'], ['scheduled_at' => $scheduled]);
            } else {
                return;
            }
            $guest->update(['last_review_requested_at' => now()]);
        }, 5);
    }

    public function eligible(Booking $booking, Guest $guest): bool
    {
        $salon = Salon::current();

        return $salon->featureEnabled('reviews') && filled($salon->review_url) && $booking->status === 'completed'
            && ! $guest->anonymized_at && $guest->review_opt_in_at && ! $guest->review_opt_out_at
            && ! $this->contactGuests($guest)->where('review_opt_out_at', '>=', $guest->review_opt_in_at)->exists();
    }

    public function unsubscribeUrl(Guest $guest): string
    {
        return URL::temporarySignedRoute('reviews.unsubscribe', now()->addYear(), ['guest' => $guest->id]);
    }

    public function unsubscribe(Guest $guest): void
    {
        DB::transaction(function () use ($guest): void {
            Salon::whereKey(1)->increment('lock_version');
            $contactIds = $this->contactGuests($guest)->pluck('id');
            Guest::whereIn('id', $contactIds)->update(['review_opt_in_at' => null, 'review_opt_out_at' => now()]);
            foreach ([OutboxMessage::class, SmsOutboxMessage::class] as $model) {
                $model::whereIn('booking_id', Booking::whereIn('guest_id', $contactIds)->select('id'))->where('kind', 'review')->whereIn('status', ['pending', 'retry', 'failed'])->update(['status' => 'obsolete']);
            }
        }, 5);
    }

    private function contactGuests(Guest $guest): Builder
    {
        return Guest::whereNull('anonymized_at')->where(function (Builder $query) use ($guest): void {
            $query->whereKey($guest->id);
            if ($guest->email) {
                $query->orWhereRaw('lower(email) = ?', [mb_strtolower($guest->email)]);
            }
            if ($guest->phone_normalized) {
                $query->orWhere('phone_normalized', $guest->phone_normalized);
            }
        });
    }
}
