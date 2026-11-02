<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('salon:prepare-test-tenant {--email=vanessa@example.test : Tesztadmin e-mail-címe}')]
#[Description('Vanessa helyi tesztpéldányának előkészítése, külső küldés nélkül')]
class PrepareTestTenant extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TenantProvisioner $provisioner): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Ez a parancs kizárólag helyi vagy tesztkörnyezetben futtatható.');

            return self::FAILURE;
        }
        $tenant = Tenant::where('slug', 'vanessa')->first()
            ?? $provisioner->create('vanessa', 'Vanessa tesztszalon', $this->option('email'));
        $this->info('Vanessa tesztpéldánya előkészítve: '.$tenant->url());
        $this->info('A főadminból belépéssel áttekinthető; saját jelszó a jelszó-visszaállítási folyamattal állítható be.');

        return self::SUCCESS;
    }
}
