<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationSender
{
    public function sendDue(): int
    {
        OutboxMessage::where('status', 'sending')->where('updated_at', '<', now()->subMinutes(5))
            ->update(['status' => 'retry', 'error_code' => 'InterruptedSend', 'scheduled_at' => now()]);
        $count = 0;
        $ids = OutboxMessage::whereIn('status', ['pending', 'retry'])->where('scheduled_at', '<=', now())->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $message = $this->claim($id);
            if (! $message) {
                continue;
            }
            $booking = Booking::with('guest', 'staff')->findOrFail($message->booking_id);
            if (! $this->current($message, $booking) || ! $booking->guest->email) {
                $this->finish($message, ['status' => 'obsolete']);

                continue;
            }
            try {
                $transport = config('mail.mailers.'.config('mail.default').'.transport');
                if (in_array($transport, ['log', 'failover', 'roundrobin'])) {
                    throw new \LogicException('Private links require a direct, non-logging transport.');
                }
                Mail::send('emails.booking', ['booking' => $booking, 'salon' => Salon::current(), 'kind' => $message->kind], function ($mail) use ($booking, $message): void {
                    $titles = ['confirmation' => 'Foglalás visszaigazolása', 'changed' => 'Időpontod módosult', 'cancelled' => 'Foglalás lemondva', 'reminder' => 'Emlékeztető az időpontodra'];
                    $mail->to($booking->guest->email)->subject($titles[$message->kind].' – '.Salon::current()->name);
                    $mail->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', 'booking-'.$booking->id.'-'.$message->revision.'-'.$message->kind.'@'.parse_url(config('app.url'), PHP_URL_HOST));
                });
                $this->finish($message, ['status' => 'sent', 'sent_at' => now(), 'error_code' => null]);
                $count++;
            } catch (\Throwable $e) {
                $obsolete = ! $this->current($message, $booking->fresh());
                $this->finish($message, ['status' => $obsolete ? 'obsolete' : ($message->attempts >= 5 ? 'failed' : 'retry'), 'error_code' => class_basename($e), 'scheduled_at' => now()->addMinutes(2 ** $message->attempts)]);
            }
        }

        return $count;
    }

    private function claim(int $id): ?OutboxMessage
    {
        return DB::transaction(function () use ($id): ?OutboxMessage {
            Salon::whereKey(1)->increment('lock_version');
            $message = OutboxMessage::lockForUpdate()->findOrFail($id);
            if (! in_array($message->status, ['pending', 'retry']) || $message->scheduled_at->isFuture()) {
                return null;
            }
            if (! $this->current($message, $message->booking)) {
                $message->update(['status' => 'obsolete']);

                return null;
            }
            if ($message->attempts >= 5) {
                $message->update(['status' => 'failed']);

                return null;
            }
            $message->update(['status' => 'sending', 'attempts' => $message->attempts + 1]);

            return $message;
        }, 5);
    }

    private function current(OutboxMessage $message, Booking $booking): bool
    {
        return $message->revision === $booking->revision && ($message->kind !== 'reminder' || ($booking->status === 'confirmed' && $booking->starts_at->isFuture()));
    }

    private function finish(OutboxMessage $message, array $values): void
    {
        OutboxMessage::whereKey($message->id)->where('status', 'sending')->where('attempts', $message->attempts)->update($values);
    }
}
