<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\VoucherRedemption;
use App\Models\WorkException;
use Carbon\CarbonImmutable;

class SalonStatistics
{
    /** @return array<string, mixed> */
    public function summarize(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $salon = Salon::current();
        $bookings = Booking::with('staff')->where('starts_at', '>=', $from->utc())->where('starts_at', '<', $until->utc())->get();
        $total = $bookings->count();
        $statuses = [];
        foreach (['confirmed' => 'Visszaigazolt', 'completed' => 'Teljesült', 'cancelled' => 'Lemondott', 'no_show' => 'Nem jelent meg'] as $key => $label) {
            $count = $bookings->where('status', $key)->count();
            $statuses[$key] = ['label' => $label, 'count' => $count, 'percent' => $total ? round($count / $total * 100, 1) : 0];
        }
        $exceptions = WorkException::where('date', '>=', $from->toDateString())->where('date', '<', $until->toDateString())->get()->groupBy('staff_id');
        $utilization = [];
        foreach (Staff::orderBy('name')->get() as $person) {
            $workMinutes = 0;
            $bookedMinutes = 0;
            $visits = $bookings->where('staff_id', $person->id)->whereIn('status', ['confirmed', 'completed', 'no_show']);
            for ($day = $from; $day->lt($until); $day = $day->addDay()) {
                $exception = $exceptions->get($person->id, collect())->first(fn ($row) => $row->date === $day->toDateString());
                $work = $exception ? $exception->hours : ($person->weekly_hours[$day->isoWeekday()] ?? []);
                foreach ($work as $range) {
                    foreach ($salon->opening_hours[$day->isoWeekday()] ?? [] as $open) {
                        $start = CarbonImmutable::parse($day->toDateString().' '.max($range[0], $open[0]), $salon->timezone)->utc();
                        $end = CarbonImmutable::parse($day->toDateString().' '.min($range[1], $open[1]), $salon->timezone)->utc();
                        if ($end->lte($start)) {
                            continue;
                        }
                        $workMinutes += $start->diffInMinutes($end);
                        $intervals = [];
                        foreach ($visits as $visit) {
                            $a = max($start->timestamp, $visit->busy_from->timestamp);
                            $b = min($end->timestamp, $visit->busy_until->timestamp);
                            if ($b > $a) {
                                $intervals[] = [$a, $b];
                            }
                        }
                        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
                        $lastEnd = 0;
                        foreach ($intervals as [$a, $b]) {
                            $bookedMinutes += max(0, $b - max($a, $lastEnd)) / 60;
                            $lastEnd = max($lastEnd, $b);
                        }
                    }
                }
            }
            $utilization[] = ['id' => $person->id, 'name' => $person->name, 'work_minutes' => (int) $workMinutes, 'booked_minutes' => (int) $bookedMinutes, 'percent' => $workMinutes ? min(100, round($bookedMinutes / $workMinutes * 100, 1)) : 0];
        }
        $channels = [];
        foreach (['online' => 'Online', 'manual' => 'Admin / telefon'] as $key => $label) {
            $count = $bookings->where('source', $key)->count();
            $channels[] = ['label' => $label, 'count' => $count, 'percent' => $total ? round($count / $total * 100, 1) : 0];
        }

        return ['total' => $total, 'statuses' => $statuses, 'channels' => $channels, 'sources' => $bookings->groupBy(fn ($booking) => $booking->referral_source ?: 'közvetlen / nem ismert')->map->count(),
            'services' => $bookings->where('status', '!=', 'cancelled')->groupBy('service_id')->map(fn ($rows) => ['name' => $rows->first()->service_name, 'count' => $rows->count()])->sortByDesc('count')->take(10),
            'utilization' => $utilization, 'amount' => $salon->featureEnabled('financials') ? (int) $bookings->where('status', 'completed')->sum('actual_amount') : null,
            'voucher_amount' => $salon->featureEnabled('financials') && $salon->featureEnabled('vouchers') ? (int) VoucherRedemption::whereIn('booking_id', $bookings->modelKeys())->whereNull('reversed_at')->sum('amount') : null];
    }
}
