<?php

use App\Services\AutomaticBookingCompletion;
use App\Services\NotificationSender;
use App\Services\OperationsHealth;
use App\Services\WaitlistOffers;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('salon:notify', function (NotificationSender $sender, OperationsHealth $health): void {
    try {
        $this->info($sender->sendDue().' értesítés feldolgozva. Hibás küldési próbálkozás: '.$sender->failedCount().'.');
    } finally {
        $health->recordRun('email');
    }
})->purpose('Esedékes tartós értesítési sor feldolgozása');
Schedule::command('salon:notify')->everyMinute()->withoutOverlapping();
Artisan::command('salon:complete-expired', function (AutomaticBookingCompletion $completion): void {
    $this->info($completion->completeDue().' foglalás automatikusan lezárva.');
})->purpose('Egy órája lejárt, visszaigazolt foglalások automatikus lezárása');
Schedule::command('salon:complete-expired')->everyMinute()->withoutOverlapping();
Artisan::command('salon:waitlist-offers', function (WaitlistOffers $offers): void {
    $prepared = $offers->prepare();
    $sent = $offers->sendDue();
    $this->info($prepared.' új ajánlat készült, '.$sent.' értesítés küldése elindult.');
})->purpose('Hozzájárult várólistás vendégek szabadhely-ajánlatainak kezelése');
Schedule::command('salon:waitlist-offers')->everyMinute()->withoutOverlapping();
Schedule::command('queue:work database --queue=password-resets --stop-when-empty --max-time=50 --tries=1')->everyMinute()->withoutOverlapping();
Schedule::command('salon:sms-notify')->everyMinute()->withoutOverlapping();
Schedule::command('salon:google-sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('salon:backup')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('salon:prune-backups --days=30')->weeklyOn(7, '03:15')->timezone('UTC')->withoutOverlapping();
