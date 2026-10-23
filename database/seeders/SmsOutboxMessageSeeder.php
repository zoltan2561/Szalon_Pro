<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\SmsOutboxMessage;
use Illuminate\Database\Seeder;

class SmsOutboxMessageSeeder extends Seeder
{
    public function run(): void
    {
        if (! Salon::current()->demo) {
            return;
        }

        $booking = Booking::first();
        if (! $booking) {
            return;
        }

        SmsOutboxMessage::firstOrCreate(
            ['booking_id' => $booking->id, 'revision' => $booking->revision, 'kind' => 'reminder'],
            ['status' => 'obsolete', 'scheduled_at' => now()]
        );
    }
}
