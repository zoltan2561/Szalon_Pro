<?php

namespace Database\Seeders;

use App\Models\LeaveRequest;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeaveRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing') || ! Salon::query()->first()?->demo) {
            return;
        }
        $staff = Staff::where('active', true)->first();
        $owner = User::where('role', 'owner')->first();
        if ($staff && $owner) {
            LeaveRequest::firstOrCreate(['staff_id' => $staff->id, 'starts_on' => now()->addWeeks(2)->toDateString(), 'ends_on' => now()->addWeeks(2)->toDateString()], ['requested_by_id' => $owner->id, 'status' => 'pending', 'note' => 'Bemutató szabadnapkérés, jóváhagyásra vár.']);
        }
    }
}
