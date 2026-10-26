<?php

namespace Database\Factories;

use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalonFactory extends Factory
{
    protected $model = Salon::class;

    public function definition(): array
    {
        return ['name' => 'Teszt Szalon', 'tagline' => 'Egy kis idő. Csak neked.', 'email' => 'salon@example.test', 'phone' => '+36301234567', 'address' => 'Teszt utca 1.', 'opening_hours' => array_fill(1, 7, [['09:00', '18:00']]), 'published' => true, 'terms' => '24 órával előbb lemondható.', 'privacy' => 'Teszt adatkezelési tájékoztató.'];
    }
}
