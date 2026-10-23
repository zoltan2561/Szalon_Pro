<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\LoyaltyEntry;
use App\Models\Salon;
use App\Services\BenefitsService;
use Illuminate\Database\Seeder;

class LoyaltyEntrySeeder extends Seeder
{
    public function run(): void
    {
        $salon = Salon::find(1);
        if (! $salon?->demo || ! $salon->featureEnabled('loyalty')) {
            return;
        }
        $booking = Booking::where('status', 'completed')->whereNotIn('id', LoyaltyEntry::where('kind', 'earned')->select('booking_id'))->first();
        if ($booking) {
            app(BenefitsService::class)->completeBooking($booking);
        }
    }
}
