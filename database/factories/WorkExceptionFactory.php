<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\WorkException;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkExceptionFactory extends Factory
{
    protected $model = WorkException::class;

    public function definition(): array
    {
        return ['staff_id' => Staff::factory(), 'date' => now()->addDay()->toDateString(), 'hours' => []];
    }
}
