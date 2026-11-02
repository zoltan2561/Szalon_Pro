<?php

namespace App\Console\Commands;

use App\Services\OperationsHealth;
use App\Services\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('salon:doctor')]
#[Description('Adatbázis, időzítő és értesítési konfiguráció ellenőrzése, módosítás és küldés nélkül')]
class DoctorSalon extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OperationsHealth $health, TenantContext $tenants): int
    {
        $hasWarnings = false;
        $tenants->each(function () use ($health, &$hasWarnings): void {
            $warnings = $health->warnings();
            foreach ($warnings as $code => $warning) {
                $this->warn($code.': '.$warning);
            }
            $hasWarnings = $hasWarnings || $warnings !== [];
        });
        if ($hasWarnings) {
            return self::FAILURE;
        }
        $this->info('A helyi működési ellenőrzések rendben. A szolgáltatói kézbesítést ez nem igazolja.');

        return self::SUCCESS;
    }
}
