<?php

namespace App\Console\Commands;

use App\Models\GoogleCalendarConnection;
use App\Services\GoogleCalendarSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('salon:google-sync')]
#[Description('Connected Google calendars: export bookings and refresh external busy periods')]
class SyncGoogleCalendars extends Command
{
    public function handle(GoogleCalendarSync $sync): int
    {
        $failures = 0;
        foreach (GoogleCalendarConnection::all() as $connection) {
            try {
                $sync->sync($connection);
            } catch (Throwable) {
                $failures++;
                $this->error('Google Naptár #'.$connection->id.': szinkronhiba.');
            }
        }

        $this->info('Google Naptár kapcsolatok: '.GoogleCalendarConnection::count().'; hibák: '.$failures.'.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
