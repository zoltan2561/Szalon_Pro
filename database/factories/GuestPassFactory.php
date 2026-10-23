<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\GuestPass;
use App\Models\PassPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestPass>
 */
class GuestPassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['guest_id' => Guest::factory(), 'pass_plan_id' => PassPlan::factory(), 'service_id' => fn (array $attributes): int => PassPlan::findOrFail($attributes['pass_plan_id'])->service_id, 'name' => fn (array $attributes): string => PassPlan::findOrFail($attributes['pass_plan_id'])->name, 'visits_total' => fn (array $attributes): int => PassPlan::findOrFail($attributes['pass_plan_id'])->visits_total, 'visits_remaining' => fn (array $attributes): int => PassPlan::findOrFail($attributes['pass_plan_id'])->visits_total, 'expires_at' => fn (array $attributes) => ($days = PassPlan::findOrFail($attributes['pass_plan_id'])->validity_days) ? now()->addDays($days) : null, 'issue_key' => fake()->uuid()];
    }
}
