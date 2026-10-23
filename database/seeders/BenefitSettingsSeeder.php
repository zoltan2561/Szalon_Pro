<?php

namespace Database\Seeders;

use App\Models\BenefitSettings;
use App\Models\Salon;
use Illuminate\Database\Seeder;

class BenefitSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (Salon::whereKey(1)->exists()) {
            BenefitSettings::firstOrCreate(['salon_id' => 1]);
        }
    }
}
