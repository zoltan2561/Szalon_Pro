<?php

use App\Models\User;
use App\Services\NotificationSender;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('salon:notify', function (NotificationSender $sender): void {
    $this->info($sender->sendDue().' értesítés feldolgozva.');
})->purpose('Esedékes tartós értesítési sor feldolgozása');
Schedule::command('salon:notify')->everyMinute()->withoutOverlapping();
Schedule::command('salon:sms-notify')->everyMinute()->withoutOverlapping();
Schedule::command('salon:google-sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('salon:backup')->dailyAt('02:15')->withoutOverlapping();
Artisan::command('salon:password {email}', function (): int {
    $password = env('SALON_ADMIN_PASSWORD');
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user || ! $password || strlen($password) < 12) {
        $this->error('Érvényes felhasználó és legalább 12 karakteres SALON_ADMIN_PASSWORD szükséges.');

        return 1;
    }
    DB::transaction(function () use ($user, $password): void {
        $user->update(['password' => $password, 'remember_token' => null]);
        DB::table('sessions')->where('user_id', $user->id)->delete();
    });
    $this->info('A jelszót módosítottuk, az adatbázisos munkameneteket megszüntettük.');

    return 0;
})->purpose('Jelszó helyreállítása operátori környezetből');
