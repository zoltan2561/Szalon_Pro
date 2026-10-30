<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class NewBookingArrived extends Notification
{
    public function __construct(public int $bookingId) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{booking_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->bookingId,
        ];
    }
}
