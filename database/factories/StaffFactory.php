<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return ['name' => fake()->name(), 'title' => 'Teszt szakember', 'active' => true, 'weekly_hours' => array_fill(1, 7, [['09:00', '18:00']])];
    }
}
