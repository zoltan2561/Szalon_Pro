<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Services\WaitlistOffers;
use Illuminate\Database\Seeder;

class WaitlistOfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('local', 'testing') && Salon::query()->first()?->demo) {
            app(WaitlistOffers::class)->prepare();
        }
    }
}
