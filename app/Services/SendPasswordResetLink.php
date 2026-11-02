<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class SendPasswordResetLink implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public readonly int $tenantId;

    public function __construct(public readonly string $email)
    {
        $this->tenantId = app(TenantContext::class)->id();
        $this->onConnection('database')->onQueue('password-resets');
    }

    public function handle(OperationsHealth $health): void
    {
        $tenant = Tenant::find($this->tenantId ?? 1);
        if (! $tenant || $tenant->status !== 'active') {
            return;
        }

        app(TenantContext::class)->run($tenant, fn () => $this->send($health));
    }

    private function send(OperationsHealth $health): void
    {
        try {
            $transport = config('mail.mailers.'.config('mail.default').'.transport');
            if (! $health->mailReady() && ! (app()->environment(['local', 'testing']) && in_array($transport, ['array', 'smtp'], true))) {
                return;
            }
            $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($this->email))])->first();
            if ($user?->isDemoViewer()) {
                return;
            }
            Password::broker()->sendResetLink(['email' => $user?->email ?? trim($this->email)], function (User $user, string $token): void {
                $user->notify(new PasswordResetLink($token));
            });
        } catch (Throwable) {
            $health->recordPasswordResetFailure();
            Log::warning('A jelszó-visszaállító levél küldése sikertelen. Ellenőrizd a levélküldő beállításait.');
        }
    }
}
