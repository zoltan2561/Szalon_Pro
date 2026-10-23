<?php

namespace Database\Seeders;

use App\Models\GoogleCalendarConnection;
use Illuminate\Database\Seeder;

class GoogleCalendarConnectionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            $this->command?->warn('A szintetikus Google-kapcsolat csak tesztkörnyezetben hozható létre.');

            return;
        }

        GoogleCalendarConnection::factory()->create();
    }
}
