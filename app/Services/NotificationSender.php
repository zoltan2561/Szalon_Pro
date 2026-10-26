<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationSender
{
    private int $failures = 0;

    public function failedCount(): int
    {
        return $this->failures;
    }

    public function sendDue(): int
    {
        $this->failures = 0;
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
                $unsubscribeUrl = $message->kind === 'review' ? app(ReviewRequests::class)->unsubscribeUrl($booking->guest) : null;
                $view = $message->kind === 'review' ? 'emails.review' : 'emails.booking';
                Mail::send(['html' => $view, 'text' => $view.'-text'], ['booking' => $booking, 'salon' => Salon::current(), 'kind' => $message->kind, 'unsubscribeUrl' => $unsubscribeUrl], function (Message $mail) use ($booking, $message, $unsubscribeUrl): void {
                    $titles = ['confirmation' => 'Foglalás visszaigazolása', 'changed' => 'Időpontod módosult', 'cancelled' => 'Foglalás lemondva', 'reminder' => 'Emlékeztető az időpontodra', 'review' => 'Hogyan érezted magad?'];
                    $mail->to($booking->guest->email)->subject($titles[$message->kind].' – '.Salon::current()->name);
                    $mail->from(config('mail.from.address'), Salon::current()->name)->replyTo(Salon::current()->email, Salon::current()->name);
                    $mail->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', 'booking-'.$booking->id.'-'.$message->revision.'-'.$message->kind.'@'.parse_url(config('app.url'), PHP_URL_HOST));
                    if ($unsubscribeUrl) {
                        $mail->getSymfonyMessage()->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribeUrl.'>');
                        $mail->getSymfonyMessage()->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                    }
                });
                $this->finish($message, ['status' => 'sent', 'sent_at' => now(), 'error_code' => null]);
                $count++;
            } catch (\Throwable $e) {
                $obsolete = ! $this->current($message, $booking->fresh());
                if (! $obsolete) {
                    $this->failures++;
                }
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
                $this->failures++;

                return null;
            }
            $message->update(['status' => 'sending', 'attempts' => $message->attempts + 1]);

            return $message;
        }, 5);
    }

    private function current(OutboxMessage $message, Booking $booking): bool
    {
        if ($message->kind === 'review') {
            return $message->revision === $booking->revision && app(ReviewRequests::class)->eligible($booking, $booking->guest);
        }

        return $message->revision === $booking->revision && ($message->kind !== 'reminder' || ($booking->status === 'confirmed' && $booking->starts_at->isFuture()));
    }

    private function finish(OutboxMessage $message, array $values): void
    {
        OutboxMessage::whereKey($message->id)->where('status', 'sending')->where('attempts', $message->attempts)->update($values);
    }
}
