<?php

namespace App\Console\Commands;

use App\Services\OperationsHealth;
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
    public function handle(OperationsHealth $health): int
    {
        $warnings = $health->warnings();
        foreach ($warnings as $code => $warning) {
            $this->warn($code.': '.$warning);
        }
        if ($warnings !== []) {
            return self::FAILURE;
        }
        $this->info('A helyi működési ellenőrzések rendben. A szolgáltatói kézbesítést ez nem igazolja.');

        return self::SUCCESS;
    }
}
