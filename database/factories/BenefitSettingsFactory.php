<?php

namespace Database\Factories;

use App\Models\BenefitSettings;
use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BenefitSettings>
 */
class BenefitSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['salon_id' => Salon::factory(), 'loyalty_points_per_visit' => 1, 'loyalty_reward_threshold' => 10, 'loyalty_reward_label' => 'Hűségjutalom'];
    }
}
