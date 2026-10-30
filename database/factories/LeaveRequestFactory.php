<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'requested_by_id' => User::factory(),
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
            'status' => 'pending',
            'note' => 'Szabadnapot szeretnék.',
        ];
    }
}
