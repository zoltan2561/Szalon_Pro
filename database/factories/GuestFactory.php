<?php

namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return ['name' => fake()->name(), 'email' => fake()->safeEmail(), 'phone' => fake()->phoneNumber()];
    }
}
