<?php

namespace App\Notifications;

use App\Models\Salon;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLink extends Notification
{
    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $salon = Salon::current();
        $tenant = app(TenantContext::class)->tenant();
        $baseUrl = $tenant && (int) $tenant->getKey() !== 1 ? $tenant->url() : rtrim(config('app.url'), '/');
        $url = $baseUrl.route('password.reset', ['token' => $this->token, 'email' => $notifiable->email], false);

        return (new MailMessage)->subject('Jelszó-visszaállítás – '.$salon->name)
            ->from(config('mail.from.address'), $salon->name)->replyTo($salon->email, $salon->name)
            ->view(['html' => 'emails.password-reset', 'text' => 'emails.password-reset-text'], ['salon' => $salon, 'resetUrl' => $url, 'expiresMinutes' => config('auth.passwords.users.expire')]);
    }
}
