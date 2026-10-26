<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('salon:reset-password {email : A helyreállítandó felhasználó e-mail-címe}')]
#[Aliases(['salon:password'])]
#[Description('Jelszó helyreállítása SALON_ADMIN_PASSWORD konfigurációból; adatbázisos munkamenetek visszavonása')]
class ResetSalonPassword extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $password = config('salon.admin_password');
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || ! is_string($password) || strlen($password) < 12 || strlen($password) > 200) {
            $this->error('Érvényes felhasználó és 12–200 karakteres SALON_ADMIN_PASSWORD szükséges.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => null])->save();
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        });
        $this->info('A jelszót módosítottuk, az érintett adatbázisos munkameneteket megszüntettük.');

        return self::SUCCESS;
    }
}
