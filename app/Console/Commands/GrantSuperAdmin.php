<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('salon:super-admin {email : Meglévő tulajdonosi fiók e-mail-címe}')]
#[Description('Meglévő központi tulajdonos főadmin jogosultságának megadása')]
class GrantSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($this->argument('email')))])->where('role', 'owner')->where('is_demo_viewer', false)->first();
        if (! $user || app(TenantContext::class)->id() !== 1) {
            $this->error('Nem található meglévő központi tulajdonosi fiók.');

            return self::FAILURE;
        }
        $user->forceFill(['is_super_admin' => true])->save();
        $this->info('A főadmin jogosultság beállítva. Belépés: /foadmin');

        return self::SUCCESS;
    }
}
