<?php

use App\Services\NotificationSender;
use App\Services\OperationsHealth;
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
Schedule::command('salon:sms-notify')->everyMinute()->withoutOverlapping();
Schedule::command('salon:google-sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('salon:backup')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('salon:prune-backups --days=30')->weeklyOn(7, '03:15')->timezone('UTC')->withoutOverlapping();
