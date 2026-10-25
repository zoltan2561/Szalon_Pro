<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\SmsOutboxMessage;
use Illuminate\Support\Facades\DB;
use Throwable;

class SmsNotificationSender
{
    public function __construct(private SmsGateway $gateway) {}

    public function queue(Booking $booking, string $kind): void
    {
        $salon = Salon::current();
        if (! in_array($kind, ['confirmation', 'changed', 'cancelled'], true) || ! $salon->featureEnabled('sms') || ! $this->gateway->configured() || ! $this->gateway->normalizeNumber($booking->guest->phone)) {
            return;
        }
        if ($salon->featureEnabled('phone_booking') && ! $booking->guest->email) {
            SmsOutboxMessage::firstOrCreate(['booking_id' => $booking->id, 'revision' => $booking->revision, 'kind' => $kind], ['scheduled_at' => now()]);
        }
        $due = $booking->starts_at->subHours($salon->reminder_hours);
        if ($booking->sms_opt_in && $booking->status === 'confirmed' && $salon->reminder_hours > 0 && $due->isFuture()) {
            SmsOutboxMessage::firstOrCreate(
                ['booking_id' => $booking->id, 'revision' => $booking->revision, 'kind' => 'reminder'],
                ['scheduled_at' => $due]
            );
        }
    }

    public function sendDue(): int
    {
        SmsOutboxMessage::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(5))
            ->update(['status' => 'uncertain', 'error_code' => 'sms_interrupted_uncertain']);
        $sent = 0;
        $ids = SmsOutboxMessage::whereIn('status', ['pending', 'retry'])->where('scheduled_at', '<=', now())->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $message = $this->claim($id);
            if (! $message) {
                continue;
            }
            $booking = Booking::with('guest')->findOrFail($message->booking_id);
            if (! $this->current($message, $booking)) {
                $this->finish($message, ['status' => 'obsolete']);

                continue;
            }
            $recipient = $this->gateway->normalizeNumber($booking->guest->phone);
            if (! $recipient || ! $this->gateway->configured()) {
                $this->finish($message, ['status' => 'failed', 'error_code' => 'sms_unavailable']);

                continue;
            }
            try {
                $result = $this->gateway->send($recipient, $this->messageBody($message, $booking));
            } catch (Throwable) {
                $result = ['outcome' => 'uncertain', 'provider_id' => null, 'error_code' => 'sms_request_uncertain'];
            }
            $values = ['status' => $result['outcome'] === 'accepted' ? 'sent' : $result['outcome'], 'provider_id' => $result['provider_id'], 'error_code' => $result['error_code']];
            if ($result['outcome'] === 'accepted') {
                $values['sent_at'] = now();
                $sent++;
            } elseif ($result['outcome'] === 'retry') {
                $values['status'] = $message->attempts >= 3 ? 'failed' : 'retry';
                $values['scheduled_at'] = now()->addMinutes(2 ** $message->attempts);
            }
            $this->finish($message, $values);
        }

        return $sent;
    }

    private function claim(int $id): ?SmsOutboxMessage
    {
        return DB::transaction(function () use ($id): ?SmsOutboxMessage {
            Salon::whereKey(1)->increment('lock_version');
            $message = SmsOutboxMessage::lockForUpdate()->findOrFail($id);
            if (! in_array($message->status, ['pending', 'retry'], true) || $message->scheduled_at->isFuture()) {
                return null;
            }
            if (! $this->current($message, $message->booking)) {
                $message->update(['status' => 'obsolete']);

                return null;
            }
            $message->update(['status' => 'sending', 'attempts' => $message->attempts + 1]);

            return $message;
        }, 5);
    }

    private function current(SmsOutboxMessage $message, Booking $booking): bool
    {
        if (! Salon::current()->featureEnabled('sms') || $booking->guest->anonymized_at || $message->revision !== $booking->revision) {
            return false;
        }

        return match ($message->kind) {
            'reminder' => $booking->sms_opt_in && $booking->status === 'confirmed' && $booking->starts_at->isFuture(),
            'confirmation', 'changed' => Salon::current()->featureEnabled('phone_booking') && ! $booking->guest->email && $booking->status === 'confirmed',
            'cancelled' => Salon::current()->featureEnabled('phone_booking') && ! $booking->guest->email && $booking->status === 'cancelled',
            default => false,
        };
    }

    private function messageBody(SmsOutboxMessage $message, Booking $booking): string
    {
        $salon = Salon::current();
        $name = preg_replace('/\s+/u', ' ', mb_substr($salon->name, 0, 24));
        $time = $booking->starts_at->setTimezone($salon->timezone)->format('Y.m.d. H:i');

        if ($message->kind === 'reminder') {
            return $name.': időpont-emlékeztető '.$time.'.';
        }
        $label = ['confirmation' => 'Foglalás visszaigazolva', 'changed' => 'Időpont módosult', 'cancelled' => 'Foglalás lemondva'][$message->kind];

        return $name.': '.$label.' '.$time.'. Bizalmas kezelési link: '.route('booking.manage', $booking->manage_token);
    }

    /** @param array<string, mixed> $values */
    private function finish(SmsOutboxMessage $message, array $values): void
    {
        SmsOutboxMessage::whereKey($message->id)->where('status', 'sending')->where('attempts', $message->attempts)->update($values);
    }
}
