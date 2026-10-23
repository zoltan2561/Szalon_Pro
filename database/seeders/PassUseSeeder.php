<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\GuestPass;
use App\Models\PassUse;
use App\Models\Salon;
use App\Services\BenefitsService;
use Illuminate\Database\Seeder;

class PassUseSeeder extends Seeder
{
    public function run(): void
    {
        $salon = Salon::find(1);
        if (! $salon?->demo || ! $salon->featureEnabled('passes')) {
            return;
        }
        $pass = GuestPass::where('visits_remaining', '>', 0)->whereNull('voided_at')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
        $booking = $pass ? Booking::where('guest_id', $pass->guest_id)->where('service_id', $pass->service_id)->where('status', 'completed')->whereNotIn('id', PassUse::select('booking_id'))->first() : null;
        if ($pass && $booking) {
            app(BenefitsService::class)->completeBooking($booking, $pass->id);
        }
    }
}
