<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingReorganization
{
    public function __construct(private Availability $availability, private ServiceSelection $services) {}

    public function authorize(Booking $booking, User $actor): void
    {
        abort_unless(($actor->role === 'owner' || $actor->can_manage_bookings) && Booking::visibleTo($actor)->whereKey($booking->id)->exists(), 403);
    }

    /** @return Collection<int, Booking> */
    public function candidates(Booking $booking, User $actor, string $date): Collection
    {
        $this->authorize($booking, $actor);
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, Salon::current()->timezone);

        return Booking::visibleTo($actor)->with('guest', 'staff')->where('status', 'confirmed')->where('id', '!=', $booking->id)
            ->where('starts_at', '>=', $day->utc())->where('starts_at', '<', $day->addDay()->utc())->orderBy('starts_at')->orderBy('id')->limit(200)->get();
    }

    /**
     * @param  array{mode: string, revision: int, shift_minutes?: ?int, swap_booking_id?: ?int}  $input
     * @return array{mode: string, available: bool, rows: list<array<string, mixed>>}
     */
    public function plan(Booking $booking, array $input, User $actor): array
    {
        $booking = $booking->fresh(['guest', 'staff']);
        $this->authorize($booking, $actor);
        if ($booking->status !== 'confirmed' || $booking->revision !== (int) $input['revision']) {
            $this->error('A foglalás időközben megváltozott vagy lezárult. Nyisd meg újra.');
        }
        $timezone = Salon::current()->timezone;
        $day = $booking->starts_at->setTimezone($timezone)->startOfDay();
        $targets = [];
        if ($input['mode'] === 'shift_day') {
            $minutes = (int) ($input['shift_minutes'] ?? 0);
            if ($minutes === 0 || abs($minutes) > 720 || $minutes % 15 !== 0) {
                $this->error('15 perces lépésekben, legfeljebb 12 órával tolhatod el a foglalásokat. A negatív szám korábbi kezdést jelent.');
            }
            $visits = Booking::with('guest', 'staff')->where('staff_id', $booking->staff_id)->where('status', 'confirmed')
                ->where('starts_at', '>=', $booking->starts_at)->where('starts_at', '<', $day->addDay()->utc())->orderBy('starts_at')->orderBy('id')->limit(101)->get();
            if ($visits->count() > 100) {
                $this->error('Egyszerre legfeljebb 100 foglalást módosíthatsz.');
            }
            foreach ($visits as $visit) {
                $targets[$visit->id] = ['staff_id' => $visit->staff_id, 'start' => $visit->starts_at->addMinutes($minutes)];
            }
        } elseif ($input['mode'] === 'swap') {
            $partner = Booking::with('guest', 'staff')->findOrFail($input['swap_booking_id'] ?? 0);
            $this->authorize($partner, $actor);
            if ($partner->id === $booking->id || $partner->status !== 'confirmed') {
                $this->error('Egy másik, még visszaigazolt foglalást válassz a cseréhez.');
            }
            $visits = new Collection([$booking, $partner]);
            $targets = [$booking->id => ['staff_id' => $partner->staff_id, 'start' => $partner->starts_at], $partner->id => ['staff_id' => $booking->staff_id, 'start' => $booking->starts_at]];
        } else {
            $this->error('Válassz eltolást vagy időpontcserét.');
        }
        $staff = Staff::whereIn('id', array_column($targets, 'staff_id'))->get()->keyBy('id');
        $rows = [];
        foreach ($visits as $visit) {
            $this->authorize($visit, $actor);
            $target = $targets[$visit->id];
            $start = $target['start'];
            $person = $staff->get($target['staff_id']);
            abort_unless($actor->role === 'owner' || $actor->staff_id === $person?->id, 403);
            $conflicts = [];
            if ($input['mode'] === 'shift_day' && $start->setTimezone($timezone)->toDateString() !== $day->toDateString()) {
                $conflicts[] = 'Az eltolás átlépné a nap elejét vagy végét.';
            }
            if (! $person || ! $this->availability->canBook($this->services->forBooking($visit), $person, $start, $visit, $visits->modelKeys())) {
                $conflicts[] = 'Erre az időpontra nem helyezhető át. Ellenőrizd a munkaidőt, a szüneteket, a szakember szolgáltatásait és a többi foglalást.';
            }
            $rows[] = ['booking_id' => $visit->id, 'revision' => $visit->revision, 'guest' => $visit->guest->name, 'service' => $visit->service_name,
                'old_staff_id' => $visit->staff_id, 'old_staff' => $visit->staff->name, 'old_starts_at' => $visit->starts_at->utc()->toIso8601String(),
                'staff_id' => $target['staff_id'], 'staff' => $person?->name ?? 'Nem elérhető szakember', 'starts_at' => $start->utc()->toIso8601String(),
                'ends_at' => $start->addMinutes($visit->duration_minutes)->utc()->toIso8601String(),
                'busy_from' => $start->subMinutes($visit->buffer_before), 'busy_until' => $start->addMinutes($visit->duration_minutes + $visit->buffer_after), 'conflicts' => $conflicts];
        }
        foreach ($rows as $index => &$row) {
            foreach ($rows as $otherIndex => $other) {
                if ($index !== $otherIndex && $row['staff_id'] === $other['staff_id'] && $row['busy_from']->lt($other['busy_until']) && $row['busy_until']->gt($other['busy_from'])) {
                    $row['conflicts'][] = 'Az új időpont egy másik módosított foglalással ütközik, az előkészületet és utómunkát is figyelembe véve.';
                    break;
                }
            }
        }
        unset($row);

        return ['mode' => $input['mode'], 'available' => collect($rows)->every(fn (array $row): bool => $row['conflicts'] === []), 'rows' => $rows];
    }

    public function token(Booking $booking, array $input, array $plan, User $actor): string
    {
        return Crypt::encryptString(json_encode(['booking_id' => $booking->id, 'actor_id' => $actor->id, 'input' => $input, 'fingerprint' => $this->fingerprint($plan), 'expires_at' => now()->addMinutes(10)->timestamp], JSON_THROW_ON_ERROR));
    }

    public function commit(Booking $booking, string $token, User $actor): int
    {
        $this->authorize($booking, $actor);
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            $this->error('Az előnézet nem érvényes. Kérj új előnézetet.');
        }
        if (! is_array($payload) || ($payload['booking_id'] ?? null) !== $booking->id || ($payload['actor_id'] ?? null) !== $actor->id || ! is_array($payload['input'] ?? null) || ! is_string($payload['fingerprint'] ?? null) || ! is_int($payload['expires_at'] ?? null) || $payload['expires_at'] <= now()->timestamp) {
            $this->error('Az előnézet lejárt vagy másik felhasználóhoz tartozik. Kérj új előnézetet.');
        }

        return DB::transaction(function () use ($booking, $payload, $actor): int {
            Salon::whereKey(1)->increment('lock_version');
            $plan = $this->plan($booking, $payload['input'], $actor);
            if (! hash_equals($payload['fingerprint'], $this->fingerprint($plan)) || ! $plan['available']) {
                $this->error('A naptár az előnézet óta megváltozott, vagy valamelyik időpont már nem szabad. Nem módosítottunk semmit. Kérj új előnézetet.');
            }
            $ids = array_column($plan['rows'], 'booking_id');
            foreach ($plan['rows'] as $row) {
                app(BookingService::class)->change(Booking::findOrFail($row['booking_id']), ['action' => 'move', 'revision' => $row['revision'], 'staff_id' => $row['staff_id'], 'starts_at' => $row['starts_at']], $actor, $ids);
            }

            return count($ids);
        }, 5);
    }

    private function fingerprint(array $plan): string
    {
        return hash('sha256', json_encode(array_map(fn (array $row): array => array_intersect_key($row, array_flip(['booking_id', 'revision', 'old_staff_id', 'old_starts_at', 'staff_id', 'starts_at'])), $plan['rows']), JSON_THROW_ON_ERROR));
    }

    private function error(string $message): never
    {
        throw ValidationException::withMessages(['reorganization' => $message]);
    }
}
