<?php

namespace Database\Factories;

use App\Models\PassPlan;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PassPlan>
 */
class PassPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['service_id' => Service::factory(), 'name' => 'Öt alkalmas bérlet', 'visits_total' => 5, 'validity_days' => 180, 'active' => true];
    }
}
