<?php

namespace App\Console\Commands;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Signature('salon:demo-user')]
#[Description('Create the read-only public demo account on a published demo installation')]
class ProvisionDemoUser extends Command
{
    public function handle(): int
    {
        $salon = Salon::find(1);
        if (! $salon?->published || ! $salon->demo) {
            $this->error('A public viewer can only be provisioned on a published demo installation.');

            return self::FAILURE;
        }
        if (! Schema::hasColumn('users', 'is_demo_viewer')) {
            $this->error('Run the application migrations before provisioning the demo viewer.');

            return self::FAILURE;
        }
        $email = mb_strtolower(trim((string) config('salon.demo_viewer_email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Configure a valid SALON_DEMO_VIEWER_EMAIL.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($email): int {
            $user = User::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            if ($user && ! $user->isDemoViewer()) {
                $this->error('This email belongs to a real account. It will not be changed.');

                return self::FAILURE;
            }
            $user ??= new User;
            if (! $user->exists) {
                $user->forceFill(['name' => 'Bemutató megtekintő', 'email' => $email, 'password' => Str::random(80)]);
            }
            $user->forceFill(['role' => 'owner', 'is_demo_viewer' => true, 'staff_id' => null, 'can_manage_bookings' => false, 'can_view_notes' => false])->save();
            $this->info('The read-only demo account is ready. No password is displayed.');

            return self::SUCCESS;
        });
    }
}
