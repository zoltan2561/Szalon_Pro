<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;

class AutomaticBookingCompletion
{
    public function __construct(private BookingService $bookings) {}

    public function completeDue(): int
    {
        $cutoff = CarbonImmutable::now()->subHour();
        $completed = 0;
        Booking::where('status', 'confirmed')->where('ends_at', '<=', $cutoff)->select('id')->chunkById(100, function ($bookings) use ($cutoff, &$completed): void {
            foreach ($bookings as $booking) {
                $completed += (int) $this->bookings->completeExpiredBooking($booking->id, $cutoff);
            }
        });

        return $completed;
    }
}
