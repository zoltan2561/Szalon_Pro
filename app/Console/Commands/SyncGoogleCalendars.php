<?php

namespace App\Console\Commands;

use App\Models\GoogleCalendarConnection;
use App\Services\GoogleCalendarSync;
use App\Services\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('salon:google-sync')]
#[Description('Kapcsolt Google-naptárak foglalásainak és külső foglaltságának frissítése')]
class SyncGoogleCalendars extends Command
{
    public function handle(GoogleCalendarSync $sync, TenantContext $tenants): int
    {
        $failures = 0;
        $connections = 0;
        $tenants->each(function () use ($sync, &$failures, &$connections): void {
            foreach (GoogleCalendarConnection::all() as $connection) {
                $connections++;
                try {
                    $sync->sync($connection);
                } catch (Throwable) {
                    $failures++;
                    $this->error('Google Naptár #'.$connection->id.': szinkronhiba.');
                }
            }
        });

        $this->info('Google Naptár kapcsolatok: '.$connections.'; hibák: '.$failures.'.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
