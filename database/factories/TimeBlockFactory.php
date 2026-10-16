<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\TimeBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeBlockFactory extends Factory
{
    protected $model = TimeBlock::class;

    public function definition(): array
    {
        return ['staff_id' => Staff::factory(), 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'reason' => 'Szünet'];
    }
}
