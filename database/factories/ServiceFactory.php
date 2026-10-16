<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return ['name' => 'Teszt szolgáltatás', 'category' => 'Teszt', 'duration_minutes' => 60, 'price' => 9500, 'price_kind' => 'fixed', 'buffer_before' => 0, 'buffer_after' => 15, 'active' => true];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Service $s): void {
            if (! $s->staff()->exists()) {
                $s->staff()->attach(Staff::factory()->create());
            }
        });
    }
}
