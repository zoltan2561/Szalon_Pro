<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GoogleCalendarBusy;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeBlock;
use App\Models\WorkException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class Availability
{
    public function parse(string $value): CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00[+-]\d{2}:\d{2}$/', $value)) {
            throw ValidationException::withMessages(['starts_at' => 'Válassz egy időpontot a szabad időpontok közül.']);
        }
        try {
            $time = CarbonImmutable::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['starts_at' => 'Érvénytelen időpont.']);
        }
        if ($time->format('Y-m-d\TH:i:sP') !== $value) {
            throw ValidationException::withMessages(['starts_at' => 'Érvénytelen időpont.']);
        }

        return $time->utc();
    }

    public function slots(Service $service, string $date, ?int $staffId = null, ?Booking $ignore = null, ?array $slotData = null): array
    {
        $salon = $slotData['salon'] ?? Salon::current();
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $salon->timezone);
        } catch (\Throwable) {
            return [];
        }
        if (! $day || $day->format('Y-m-d') !== $date || ! $service->active) {
            return [];
        }
        $slotData ??= $this->loadSlotData($service, $day, $day->addDay(), $staffId, $ignore);
        $staff = $slotData['staff'];
        $exceptions = $slotData['exceptions']->get($date, collect())->keyBy('staff_id');
        $blocks = $slotData['blocks'];
        $bookings = $slotData['bookings'];
        $googleBusy = $slotData['googleBusy'];
        $minimumStart = CarbonImmutable::now()->addMinutes($salon->min_notice_minutes);
        $lastDay = CarbonImmutable::now($salon->timezone)->addDays($salon->horizon_days)->toDateString();
        $slots = [];
        foreach ($staff as $person) {
            $connection = $person->googleCalendarConnection;
            if ($connection && ($connection->sync_status !== 'connected' || ! $connection->last_imported_at || $connection->last_imported_at->lt(now()->subMinutes(15)))) {
                continue;
            }
            $work = $exceptions->has($person->id) ? $exceptions[$person->id]->hours : ($person->weekly_hours[$day->isoWeekday()] ?? []);
            $open = $salon->opening_hours[$day->isoWeekday()] ?? [];
            $dayStart = $day->utc();
            $dayEnd = $day->addDay()->utc();
            $personBlocks = $blocks[$date][$person->id] ?? collect();
            $personBookings = $bookings[$date][$person->id] ?? collect();
            $personGoogleBusy = $googleBusy[$date][$connection?->id] ?? collect();
            if ($this->workdayFullyBlocked($day, $work, $open, $personBlocks, $personBookings, $personGoogleBusy, $service->duration_minutes + $service->buffer_before + $service->buffer_after)) {
                continue;
            }
            for ($time = $dayStart; $time->lt($dayEnd); $time = $time->addMinutes(15)) {
                $local = $time->setTimezone($salon->timezone);
                $from = $time->subMinutes($service->buffer_before);
                $until = $time->addMinutes($service->duration_minutes + $service->buffer_after);
                if ($time->gte($minimumStart) && $date <= $lastDay && $local->minute % 15 === 0
                    && $this->inside($from, $until, $work, $local) && $this->inside($from, $until, $open, $local)
                    && ! $personBlocks->contains(fn ($block) => $block->starts_at->lt($until) && $block->ends_at->gt($from))
                    && ! $personBookings->contains(fn ($booking) => $booking->busy_from->lt($until) && $booking->busy_until->gt($from))
                    && ! $personGoogleBusy->contains(fn ($busy) => $busy->starts_at->lt($until) && $busy->ends_at->gt($from))) {
                    $slots[] = ['starts_at' => $local->format('Y-m-d\TH:i:sP'), 'staff_id' => $person->id, 'staff_name' => $person->name, 'label' => $local->format('H:i'), 'offset' => $local->format('P')];
                }
            }
        }
        usort($slots, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']) ?: $a['staff_id'] <=> $b['staff_id']);

        return $slots;
    }

    public function firstAvailableDay(Service $service, CarbonImmutable $start, CarbonImmutable $end, ?int $staffId = null): ?array
    {
        for ($chunkStart = $start; $chunkStart->lte($end); $chunkStart = $chunkEnd) {
            $chunkEnd = $chunkStart->addDays(30)->min($end->addDay());
            $data = $this->loadSlotData($service, $chunkStart, $chunkEnd, $staffId);
            if ($data['staff']->isEmpty()) {
                return null;
            }
            $openingHours = $data['salon']->opening_hours;
            for ($day = $chunkStart; $day->lt($chunkEnd); $day = $day->addDay()) {
                if (! ($openingHours[$day->isoWeekday()] ?? [])) {
                    continue;
                }
                $date = $day->toDateString();
                $slots = $this->slots($service, $date, $staffId, slotData: $data);
                if ($slots) {
                    return ['date' => $date, 'slots' => $slots];
                }
            }
        }

        return null;
    }

    private function loadSlotData(Service $service, CarbonImmutable $start, CarbonImmutable $end, ?int $staffId = null, ?Booking $ignore = null): array
    {
        $staff = $service->staff()->where('active', true)->when($service->relationLoaded('selectedServices'), fn ($query) => $query->whereIn('staff.id', $service->staff->modelKeys()))->when($staffId, fn ($q) => $q->where('staff.id', $staffId))->with('googleCalendarConnection')->get();
        $staffIds = $staff->modelKeys();
        $exceptions = WorkException::whereIn('staff_id', $staffIds)->where('date', '>=', $start->toDateString())->where('date', '<', $end->toDateString())->get()->groupBy('date');
        $blocks = TimeBlock::whereIn('staff_id', $staffIds)->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->get();
        $bookings = Booking::whereIn('staff_id', $staffIds)->whereIn('status', ['confirmed', 'completed', 'no_show'])->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
            ->where('busy_from', '<', $end->utc())->where('busy_until', '>', $start->utc())->get();
        $connectionIds = $staff->map(fn (Staff $person) => $person->googleCalendarConnection?->id)->filter()->all();
        $googleBusy = GoogleCalendarBusy::whereIn('google_calendar_connection_id', $connectionIds)->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc())->get();

        $salon = Salon::current();
        $blocks = $this->indexIntervalsByDay($blocks, $start, $end, 'staff_id', 'starts_at', 'ends_at');
        $bookings = $this->indexIntervalsByDay($bookings, $start, $end, 'staff_id', 'busy_from', 'busy_until');
        $googleBusy = $this->indexIntervalsByDay($googleBusy, $start, $end, 'google_calendar_connection_id', 'starts_at', 'ends_at');

        return compact('staff', 'exceptions', 'blocks', 'bookings', 'googleBusy', 'salon');
    }

    private function indexIntervalsByDay(Collection $items, CarbonImmutable $start, CarbonImmutable $end, string $ownerField, string $fromField, string $untilField): array
    {
        $index = [];
        foreach ($items as $item) {
            $from = $item->{$fromField};
            $until = $item->{$untilField};
            $day = $from->setTimezone($start->timezone)->startOfDay()->max($start);
            while ($day->lt($end) && $day->utc()->lt($until)) {
                $index[$day->toDateString()][$item->{$ownerField}][] = $item;
                $day = $day->addDay();
            }
        }
        foreach ($index as $date => $owners) {
            foreach ($owners as $owner => $rows) {
                $index[$date][$owner] = collect($rows);
            }
        }

        return $index;
    }

    private function workdayFullyBlocked(CarbonImmutable $day, array $work, array $open, Collection $blocks, Collection $bookings, Collection $googleBusy, int $requiredMinutes): bool
    {
        if ($day->addDay()->utc()->timestamp - $day->utc()->timestamp !== 86400) {
            return false;
        }
        $intervals = [];
        foreach ($blocks as $block) {
            $intervals[] = [$block->starts_at->timestamp, $block->ends_at->timestamp];
        }
        foreach ($bookings as $booking) {
            $intervals[] = [$booking->busy_from->timestamp, $booking->busy_until->timestamp];
        }
        foreach ($googleBusy as $busy) {
            $intervals[] = [$busy->starts_at->timestamp, $busy->ends_at->timestamp];
        }
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        foreach ($work as $range) {
            foreach ($open as $opening) {
                $startTime = max($range[0], $opening[0]);
                $endTime = min($range[1], $opening[1]);
                if ($startTime >= $endTime) {
                    continue;
                }
                $coveredUntil = CarbonImmutable::parse($day->toDateString().' '.$startTime, $day->timezone)->timestamp;
                $end = CarbonImmutable::parse($day->toDateString().' '.$endTime, $day->timezone)->timestamp;
                foreach ($intervals as [$from, $until]) {
                    if ($from - $coveredUntil >= $requiredMinutes * 60) {
                        return false;
                    }
                    $coveredUntil = max($coveredUntil, $until);
                    if ($coveredUntil >= $end) {
                        break;
                    }
                }
                if ($end - $coveredUntil >= $requiredMinutes * 60) {
                    return false;
                }
            }
        }

        return true;
    }

    public function canBook(Service $service, Staff $staff, CarbonImmutable $start, ?Booking $ignore = null, array $ignoreBookingIds = []): bool
    {
        $start = $start->utc();
        $salon = Salon::query()->findOrFail(1);
        $local = $start->setTimezone($salon->timezone);
        if (! $service->active || ! $staff->active || ! $service->staff()->whereKey($staff->id)->exists() || ! app(ServiceSelection::class)->eligible($service, $staff)
            || $start->lt(CarbonImmutable::now()->addMinutes($salon->min_notice_minutes))
            || $local->toDateString() > CarbonImmutable::now($salon->timezone)->addDays($salon->horizon_days)->toDateString()
            || $local->minute % 15 !== 0 || $local->second !== 0) {
            return false;
        }
        $from = $start->subMinutes($service->buffer_before);
        $until = $start->addMinutes($service->duration_minutes + $service->buffer_after);
        $exception = WorkException::where('staff_id', $staff->id)->where('date', $local->toDateString())->first();
        $work = $exception ? $exception->hours : ($staff->weekly_hours[$local->isoWeekday()] ?? []);
        $open = $salon->opening_hours[$local->isoWeekday()] ?? [];
        if (! $this->inside($from, $until, $work, $local) || ! $this->inside($from, $until, $open, $local)) {
            return false;
        }
        if (TimeBlock::where('staff_id', $staff->id)->where('starts_at', '<', $until)->where('ends_at', '>', $from)->exists()) {
            return false;
        }

        $staff->loadMissing('googleCalendarConnection');
        $connection = $staff->googleCalendarConnection;
        if ($connection) {
            if ($connection->sync_status !== 'connected' || ! $connection->last_imported_at || $connection->last_imported_at->lt(now()->subMinutes(15))) {
                return false;
            }
            if (GoogleCalendarBusy::where('google_calendar_connection_id', $connection->id)->where('starts_at', '<', $until)->where('ends_at', '>', $from)->exists()) {
                return false;
            }
        }

        return ! Booking::where('staff_id', $staff->id)->whereIn('status', ['confirmed', 'completed', 'no_show'])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->when($ignoreBookingIds !== [], fn ($query) => $query->whereNotIn('id', $ignoreBookingIds))
            ->where('busy_from', '<', $until)->where('busy_until', '>', $from)->exists();
    }

    private function inside(CarbonImmutable $from, CarbonImmutable $until, array $ranges, CarbonImmutable $day): bool
    {
        foreach ($ranges as $range) {
            $a = CarbonImmutable::parse($day->toDateString().' '.$range[0], $day->timezone);
            $b = CarbonImmutable::parse($day->toDateString().' '.$range[1], $day->timezone);
            if ($from->gte($a) && $until->lte($b)) {
                return true;
            }
        }

        return false;
    }

    public function ready(): bool
    {
        foreach (Service::where('active', true)->with('staff')->get() as $service) {
            foreach ($service->staff->where('active', true) as $staff) {
                foreach ($staff->weekly_hours as $day => $ranges) {
                    foreach ($ranges as $range) {
                        foreach (Salon::current()->opening_hours[$day] ?? [] as $opening) {
                            $start = max(strtotime($range[0]), strtotime($opening[0]));
                            $end = min(strtotime($range[1]), strtotime($opening[1]));
                            if (($end - $start) / 60 >= $service->duration_minutes + $service->buffer_before + $service->buffer_after) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }
}
