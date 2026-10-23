<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\PassPlan;
use App\Models\Salon;
use App\Models\User;
use App\Services\BenefitsService;
use Illuminate\Database\Seeder;

class GuestPassSeeder extends Seeder
{
    public function run(): void
    {
        $salon = Salon::find(1);
        if (! $salon?->demo || ! $salon->featureEnabled('passes')) {
            return;
        }
        $guest = Guest::whereNull('anonymized_at')->first();
        $plan = PassPlan::where('active', true)->whereHas('service', fn ($query) => $query->where('active', true))->first();
        $owner = User::where('role', 'owner')->first();
        if ($guest && $plan && $owner) {
            app(BenefitsService::class)->issuePass($guest, $plan, $owner, 'demo-guest-pass-'.$guest->id.'-'.$plan->id);
        }
    }
}
