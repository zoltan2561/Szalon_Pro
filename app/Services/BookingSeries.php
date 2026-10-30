<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingSeries
{
    public function __construct(private Availability $availability) {}

    /** @return list<array{starts_at: string, label: string, available: bool}> */
    public function preview(Service $service, Staff $staff, CarbonImmutable $start, int $weeks, int $count): array
    {
        $this->validatePattern($weeks, $count);
        $local = $start->setTimezone(Salon::current()->timezone);
        $results = [];
        for ($index = 0; $index < $count; $index++) {
            $candidate = $local->addWeeks($weeks * $index);
            $results[] = [
                'starts_at' => $candidate->format('Y-m-d\TH:i:sP'),
                'label' => $candidate->locale('hu')->isoFormat('YYYY. MMMM D., dddd · HH:mm'),
                'available' => $candidate->format('H:i') === $local->format('H:i') && $this->availability->canBook($service, $staff, $candidate),
            ];
        }

        return $results;
    }

    public function create(array $data, ?User $actor = null, ?Guest $guest = null): Booking
    {
        $count = (int) ($data['repeat_count'] ?? 1);
        $weeks = (int) ($data['repeat_weeks'] ?? 4);
        $this->validatePattern($weeks, $count);

        $data['repeat_count'] = $count;
        $data['repeat_weeks'] = $weeks;

        return DB::transaction(function () use ($data, $actor, $guest, $count, $weeks): Booking {
            Salon::whereKey(1)->increment('lock_version');
            $bookings = app(BookingService::class);
            $first = $bookings->create($data + ['_series_occurrence' => true], $actor, $guest);
            if ($first->series_key) {
                return $first;
            }
            $key = Str::uuid()->toString();
            $first->update(['series_key' => $key, 'series_position' => 1, 'series_interval_weeks' => $weeks]);
            $local = $first->starts_at->setTimezone(Salon::current()->timezone);
            for ($index = 1; $index < $count; $index++) {
                $candidate = $local->addWeeks($weeks * $index);
                if ($candidate->format('H:i') !== $local->format('H:i')) {
                    throw ValidationException::withMessages(['repeat_count' => 'Az óraátállítás miatt az egyik alkalom időpontja nem létezik. Válassz másik kezdési időt.']);
                }
                $next = array_replace($data, ['_series_occurrence' => true, 'starts_at' => $candidate->format('Y-m-d\TH:i:sP'), 'request_key' => Str::uuid()->toString()]);
                if ($actor) {
                    $next['guest_id'] = $first->guest_id;
                }
                try {
                    $booking = $bookings->create($next, $actor, $actor ? null : $first->guest);
                } catch (ValidationException $error) {
                    throw ValidationException::withMessages(['repeat_count' => $candidate->format('Y. m. d. H:i').' nem foglalható. Egyik alkalmat sem mentettük. Válassz másik kezdést vagy kevesebb alkalmat.']);
                }
                $booking->update(['series_key' => $key, 'series_position' => $index + 1, 'series_interval_weeks' => $weeks]);
            }

            return $first;
        }, 5);
    }

    public function changeFollowing(Booking $booking, array $data, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $data, $actor): Booking {
            Salon::whereKey(1)->increment('lock_version');
            $current = Booking::findOrFail($booking->id);
            abort_unless($current->series_key && in_array($data['action'], ['move', 'cancel'], true), 422);
            if ($current->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'A sorozat időközben változott. Frissítsd az oldalt.']);
            }
            $visits = Booking::where('series_key', $current->series_key)->where('guest_id', $current->guest_id)->where('starts_at', '>=', $current->starts_at)->where('starts_at', '>', now())->where('status', 'confirmed')->orderBy('starts_at')->get();
            if ($visits->isEmpty()) {
                throw ValidationException::withMessages(['action' => 'Nincs módosítható jövőbeli alkalom ebben a sorozatban.']);
            }
            $targets = [];
            $intervals = [];
            if ($data['action'] === 'move') {
                $original = $current->starts_at->setTimezone(Salon::current()->timezone);
                $selected = $this->availability->parse($data['starts_at'])->setTimezone(Salon::current()->timezone);
                $days = (int) $original->startOfDay()->diffInDays($selected->startOfDay(), false);
                $minutes = $selected->hour * 60 + $selected->minute - ($original->hour * 60 + $original->minute);
                foreach ($visits as $visit) {
                    $local = $visit->starts_at->setTimezone(Salon::current()->timezone);
                    $clock = $local->hour * 60 + $local->minute + $minutes;
                    $carry = (int) floor($clock / 1440);
                    $clock = ($clock % 1440 + 1440) % 1440;
                    $target = $local->startOfDay()->addDays($days + $carry)->setTime(intdiv($clock, 60), $clock % 60);
                    if ($target->format('H:i') !== sprintf('%02d:%02d', intdiv($clock, 60), $clock % 60)) {
                        throw ValidationException::withMessages(['slot' => 'Az óraátállítás miatt az egyik időpont nem létezik. Válassz másik kezdést.']);
                    }
                    $from = $target->utc()->subMinutes($visit->buffer_before);
                    $until = $target->utc()->addMinutes($visit->duration_minutes + $visit->buffer_after);
                    foreach ($intervals as [$otherFrom, $otherUntil]) {
                        if ($from->lt($otherUntil) && $until->gt($otherFrom)) {
                            throw ValidationException::withMessages(['slot' => 'Az áthelyezés után a sorozat alkalmai ütköznének. Módosítsd őket külön.']);
                        }
                    }
                    $intervals[] = [$from, $until];
                    $targets[$visit->id] = $target->format('Y-m-d\TH:i:sP');
                }
            }
            foreach ($visits as $visit) {
                $change = ['action' => $data['action'], 'revision' => $visit->revision];
                if ($data['action'] === 'move') {
                    $change += ['staff_id' => $data['staff_id'], 'starts_at' => $targets[$visit->id]];
                }
                app(BookingService::class)->change($visit, $change, $actor, $visits->modelKeys());
            }

            return $current->fresh();
        }, 5);
    }

    private function validatePattern(int $weeks, int $count): void
    {
        if (! in_array($weeks, [1, 2, 3, 4, 6, 8], true) || $count < 1 || $count > 6) {
            throw ValidationException::withMessages(['repeat_count' => 'Legfeljebb 6 alkalmat, 1, 2, 3, 4, 6 vagy 8 hetente foglalhatsz.']);
        }
    }
}
