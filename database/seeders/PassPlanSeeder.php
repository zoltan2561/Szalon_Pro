<?php

namespace Database\Seeders;

use App\Models\PassPlan;
use App\Models\Salon;
use App\Models\Service;
use Illuminate\Database\Seeder;

class PassPlanSeeder extends Seeder
{
    public function run(): void
    {
        if (! Salon::whereKey(1)->where('demo', true)->exists()) {
            return;
        }
        $service = Service::where('active', true)->first();
        if ($service) {
            PassPlan::firstOrCreate(['service_id' => $service->id, 'name' => 'Bemutató 5 alkalmas bérlet'], ['visits_total' => 5, 'validity_days' => 180, 'active' => true]);
        }
    }
}
