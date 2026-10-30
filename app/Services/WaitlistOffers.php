<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WaitlistOffers
{
    public function __construct(private Availability $availability, private SmsGateway $sms, private OperationsHealth $health) {}

    public function configured(): bool
    {
        return $this->health->mailReady() || (Salon::current()->featureEnabled('sms') && $this->sms->configured());
    }

    public function enabled(): bool
    {
        $salon = Salon::current();

        return $salon->published && $salon->featureEnabled('waitlist') && $salon->featureEnabled('waitlist_auto') && $this->configured();
    }

    public function prepare(): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        WaitlistOffer::whereIn('status', ['pending', 'retry', 'sent'])->where('expires_at', '<=', now())->update(['status' => 'expired']);
        $ids = WaitlistEntry::where('status', 'waiting')->whereNotNull('notification_consent_at')->where(fn ($query) => $query->whereNull('last_offered_at')->orWhere('last_offered_at', '<=', now()->subDay()))->orderBy('created_at')->limit(100)->pluck('id');
        $created = 0;
        foreach ($ids as $id) {
            $created += (int) DB::transaction(function () use ($id): bool {
                Salon::whereKey(1)->increment('lock_version');
                $entry = WaitlistEntry::with('guest', 'service.staff')->lockForUpdate()->find($id);
                if (! $entry || $entry->status !== 'waiting' || ! $entry->notification_consent_at || $entry->guest->anonymized_at || ! $entry->service->active || ($entry->last_offered_at && $entry->last_offered_at->gt(now()->subDay()))) {
                    return false;
                }
                $channel = $entry->guest->email && $this->health->mailReady() ? 'email' : (($this->sms->configured() && Salon::current()->featureEnabled('sms') && $this->sms->normalizeNumber($entry->guest->phone)) ? 'sms' : null);
                if (! $channel || (! $entry->guest->email && ! Salon::current()->featureEnabled('phone_booking'))) {
                    return false;
                }
                $today = CarbonImmutable::now(Salon::current()->timezone)->startOfDay();
                $start = $entry->preferred_date ? $entry->preferred_date->max($today) : $today;
                $end = ($entry->preferred_until ?? $entry->preferred_date ?? $start->addDays(13))->min($today->addDays(Salon::current()->horizon_days))->min($start->addDays(30));
                for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                    foreach ($this->availability->slots($entry->service, $day->toDateString(), $entry->staff_id) as $slot) {
                        $candidate = $this->availability->parse($slot['starts_at'])->setTimezone(Salon::current()->timezone);
                        if (($entry->time_from && $candidate->format('H:i') < $entry->time_from) || ($entry->time_until && $candidate->addMinutes($entry->service->duration_minutes)->format('H:i') > $entry->time_until)) {
                            continue;
                        }
                        if (WaitlistOffer::where('waitlist_entry_id', $entry->id)->where('staff_id', $slot['staff_id'])->where('starts_at', $candidate->utc())->exists()) {
                            continue;
                        }
                        $token = bin2hex(random_bytes(32));
                        WaitlistOffer::create(['waitlist_entry_id' => $entry->id, 'staff_id' => $slot['staff_id'], 'starts_at' => $candidate->utc(), 'expires_at' => now()->addMinutes(30), 'token_hash' => hash('sha256', $token), 'manage_token' => $token, 'channel' => $channel, 'status' => 'pending', 'scheduled_at' => now()]);
                        $entry->update(['last_offered_at' => now()]);

                        return true;
                    }
                }

                return false;
            }, 5);
        }

        return $created;
    }

    public function sendDue(): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        WaitlistOffer::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(5))->update(['status' => 'uncertain', 'last_error' => 'interrupted_delivery']);
        $sent = 0;
        foreach (WaitlistOffer::whereIn('status', ['pending', 'retry'])->where('scheduled_at', '<=', now())->orderBy('id')->limit(50)->pluck('id') as $id) {
            $offer = DB::transaction(function () use ($id): ?WaitlistOffer {
                Salon::whereKey(1)->increment('lock_version');
                $offer = WaitlistOffer::with('entry.guest', 'entry.service', 'staff')->lockForUpdate()->find($id);
                if (! $offer || ! in_array($offer->status, ['pending', 'retry'], true) || $offer->scheduled_at->isFuture()) {
                    return null;
                }
                if (! $this->valid($offer) || $offer->attempts >= 3) {
                    $offer->update(['status' => 'expired']);

                    return null;
                }
                $offer->update(['status' => 'sending', 'attempts' => $offer->attempts + 1]);

                return $offer;
            }, 5);
            if (! $offer) {
                continue;
            }
            $values = [];
            try {
                if ($offer->channel === 'email' && $this->health->mailReady()) {
                    Mail::send(['html' => 'emails.waitlist-offer', 'text' => 'emails.waitlist-offer-text'], ['offer' => $offer, 'salon' => Salon::current()], function (Message $mail) use ($offer): void {
                        $mail->to($offer->entry->guest->email)->subject('Szabad időpontot találtunk – '.Salon::current()->name);
                        $mail->from(config('mail.from.address'), Salon::current()->name)->replyTo(Salon::current()->email, Salon::current()->name);
                        $mail->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', 'waitlist-'.$offer->id.'@'.parse_url(config('app.url'), PHP_URL_HOST));
                    });
                    $values = ['status' => 'sent', 'sent_at' => now(), 'last_error' => null];
                } elseif ($offer->channel === 'sms' && $this->sms->configured() && Salon::current()->featureEnabled('sms')) {
                    $recipient = $this->sms->normalizeNumber($offer->entry->guest->phone);
                    if (! $recipient) {
                        WaitlistOffer::whereKey($offer->id)->where('status', 'sending')->update(['status' => 'failed', 'last_error' => 'phone_invalid']);

                        continue;
                    }
                    $body = Salon::current()->name.': szabad időpont '.$offer->starts_at->setTimezone(Salon::current()->timezone)->format('m.d. H:i').'. Részletek és foglalás: '.route('waitlist.offer', $offer->manage_token);
                    $result = $this->sms->send($recipient, $body);
                    $values = ['status' => $result['outcome'] === 'accepted' ? 'sent' : $result['outcome'], 'sent_at' => $result['outcome'] === 'accepted' ? now() : null, 'last_error' => $result['error_code']];
                    if ($result['outcome'] === 'retry') {
                        $values['scheduled_at'] = now()->addMinutes(2 ** $offer->attempts);
                    }
                } else {
                    $values = ['status' => 'failed', 'last_error' => 'channel_unavailable'];
                }
            } catch (\Throwable) {
                $values = ['status' => 'uncertain', 'last_error' => 'delivery_uncertain'];
            }
            WaitlistOffer::whereKey($offer->id)->where('status', 'sending')->update($values);
            $sent += (int) ($values['status'] === 'sent');
        }

        return $sent;
    }

    public function valid(WaitlistOffer $offer): bool
    {
        return Salon::current()->published && in_array($offer->status, ['pending', 'retry', 'sending', 'sent', 'uncertain'], true) && $offer->expires_at->isFuture() && $offer->entry->status === 'waiting' && ! $offer->entry->guest->anonymized_at && $offer->entry->notification_consent_at
            && $this->availability->canBook($offer->entry->service, $offer->staff, $offer->starts_at);
    }

    public function accept(WaitlistOffer $offer): Booking
    {
        return DB::transaction(function () use ($offer): Booking {
            Salon::whereKey(1)->increment('lock_version');
            $current = WaitlistOffer::with('entry.guest', 'entry.service', 'staff')->lockForUpdate()->findOrFail($offer->id);
            if (! $current->expires_at->isFuture()) {
                throw ValidationException::withMessages(['offer' => 'Az ajánlat lejárt. A várólistán továbbra is szerepelsz.']);
            }
            if ($current->status === 'accepted' && $current->booking) {
                return $current->booking;
            }
            if (! Salon::current()->featureEnabled('waitlist') || ! $this->valid($current)) {
                throw ValidationException::withMessages(['offer' => 'Ez az időpont már nem foglalható. Nem jött létre új foglalás; a várólistán továbbra is szerepelsz.']);
            }
            $entry = WaitlistEntry::lockForUpdate()->findOrFail($current->waitlist_entry_id);
            $guest = $entry->guest;
            $data = ['service_id' => $entry->service_id, 'staff_id' => $current->staff_id, 'starts_at' => $current->starts_at->format('Y-m-d\TH:i:sP'), 'name' => $guest->name, 'email' => $guest->email, 'phone' => $guest->phone, 'request_key' => Str::uuid()->toString()];
            $booking = app(BookingService::class)->create($data, null, $guest);
            $entry->update(['status' => 'booked', 'booked_booking_id' => $booking->id]);
            $current->update(['status' => 'accepted', 'booking_id' => $booking->id, 'accepted_at' => now()]);
            WaitlistOffer::where('waitlist_entry_id', $entry->id)->where('id', '!=', $current->id)->whereIn('status', ['pending', 'retry', 'sent'])->update(['status' => 'obsolete']);

            return $booking;
        }, 5);
    }
}
