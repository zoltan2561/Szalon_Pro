<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeBlock;
use App\Models\WorkException;
use Carbon\CarbonImmutable;
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

    public function slots(Service $service, string $date, ?int $staffId = null, ?Booking $ignore = null): array
    {
        $salon = Salon::current();
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $salon->timezone);
        } catch (\Throwable) {
            return [];
        }
        if (! $day || $day->format('Y-m-d') !== $date || ! $service->active) {
            return [];
        }
        $staff = $service->staff()->where('active', true)->when($staffId, fn ($q) => $q->where('staff.id', $staffId))->get();
        $slots = [];
        foreach ($staff as $person) {
            for ($time = $day->utc(); $time->lt($day->addDay()->utc()); $time = $time->addMinutes(15)) {
                if ($this->canBook($service, $person, $time, $ignore)) {
                    $local = $time->setTimezone($salon->timezone);
                    $slots[] = ['starts_at' => $local->format('Y-m-d\TH:i:sP'), 'staff_id' => $person->id, 'staff_name' => $person->name, 'label' => $local->format('H:i'), 'offset' => $local->format('P')];
                }
            }
        }
        usort($slots, fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']) ?: $a['staff_id'] <=> $b['staff_id']);

        return $slots;
    }

    public function canBook(Service $service, Staff $staff, CarbonImmutable $start, ?Booking $ignore = null): bool
    {
        $salon = Salon::current();
        $local = $start->setTimezone($salon->timezone);
        if (! $service->active || ! $staff->active || ! $service->staff()->whereKey($staff->id)->exists()
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

        return ! Booking::where('staff_id', $staff->id)->whereIn('status', ['confirmed', 'completed', 'no_show'])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
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
