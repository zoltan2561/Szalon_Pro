<?php

namespace App\Notifications;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChanged extends Notification
{
    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $salon = Salon::current();

        return (new MailMessage)->subject('A jelszavad megváltozott – '.$salon->name)
            ->from(config('mail.from.address'), $salon->name)->replyTo($salon->email, $salon->name)
            ->view(['html' => 'emails.password-changed', 'text' => 'emails.password-changed-text'], ['salon' => $salon]);
    }
}
