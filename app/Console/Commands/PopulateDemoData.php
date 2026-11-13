<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('salon:demo-data {--refresh : Csak a korábban ezzel a paranccsal létrehozott mintavendégeket és foglalásaikat cseréli}')]
#[Description('Élethű, szintetikus foglalásokkal tölti fel a publikált demó szalont (valós telepítésen nem fut)')]
class PopulateDemoData extends Command
{
    private const GuestEmailPrefix = 'demo-minta-';

    private const GuestEmailDomain = '@example.test';

    private const Names = [
        'Kovács Anna', 'Nagy Eszter', 'Szabó Petra', 'Tóth Barbara', 'Horváth Dóra', 'Varga Zsófia', 'Kiss Bence', 'Molnár Gergő',
        'Farkas Lilla', 'Papp Réka', 'Takács Mónika', 'Juhász Noémi', 'Lakatos Márk', 'Simon Kata', 'Rácz Dávid', 'Fehér Orsolya',
        'Balogh Tamás', 'Gál Vivien', 'Oláh Bálint', 'Pintér Hanna', 'Kelemen Ádám', 'Hegedűs Fanni', 'Szűcs Júlia', 'Mészáros Levente',
    ];

    private const PastDays = 21;

    private const FutureDays = 14;

    private const MinimumExistingBookings = 20;

    public function handle(): int
    {
        $salon = Salon::query()->first();
        if (! $salon?->published || ! $salon->demo) {
            $this->error('Mintaadat csak publikált demó telepítésen hozható létre; valós szalont ez a parancs nem érint.');

            return self::FAILURE;
        }
        $services = Service::where('active', true)->with('staff')->orderBy('sort_order')->get()->filter(fn (Service $service): bool => $service->staff->where('active', true)->isNotEmpty())->values();
        if ($services->isEmpty()) {
            $this->error('A demóhoz legalább egy aktív szolgáltatás és egy hozzá tartozó aktív szakember szükséges.');

            return self::FAILURE;
        }
        $syntheticGuestIds = Guest::where('email', 'like', self::GuestEmailPrefix.'%'.self::GuestEmailDomain)->pluck('id');
        if ($this->option('refresh')) {
            $this->removeSyntheticData($syntheticGuestIds->all());
        } elseif ($syntheticGuestIds->isNotEmpty() || Booking::count() >= self::MinimumExistingBookings) {
            $this->info('A demó már tartalmaz elég foglalást; nem módosítottunk semmit. A mintaadat cseréjéhez használd a --refresh kapcsolót.');

            return self::SUCCESS;
        }

        $created = DB::transaction(fn (): int => $this->populate($salon, $services));
        $this->info($created.' szintetikus mintafoglalás készült. Nem küldtünk értesítést.');

        return self::SUCCESS;
    }

    /** @param  list<int>  $guestIds */
    private function removeSyntheticData(array $guestIds): void
    {
        DB::transaction(function () use ($guestIds): void {
            $bookingIds = Booking::whereIn('guest_id', $guestIds)->pluck('id');
            BookingEvent::whereIn('booking_id', $bookingIds)->delete();
            Booking::whereIn('id', $bookingIds)->delete();
            Guest::whereIn('id', $guestIds)->delete();
        });
    }

    /** @param  Collection<int, Service>  $services */
    private function populate(Salon $salon, $services): int
    {
        $timezone = $salon->timezone;
        $now = CarbonImmutable::now($timezone);
        $guests = collect(self::Names)->map(fn (string $name, int $index): Guest => Guest::create([
            'name' => $name,
            'email' => self::GuestEmailPrefix.($index + 1).self::GuestEmailDomain,
            'phone' => '+3620000'.str_pad((string) (100 + $index), 4, '0', STR_PAD_LEFT),
        ]));
        $staffMembers = Staff::where('active', true)->orderBy('sort_order')->get();
        $created = 0;
        $sequence = 0;

        for ($offset = -self::PastDays; $offset <= self::FutureDays; $offset++) {
            $day = $now->startOfDay()->addDays($offset);
            $open = $salon->opening_hours[$day->isoWeekday()] ?? [];
            if ($open === []) {
                continue;
            }
            foreach ($staffMembers as $staff) {
                $staffServices = $services->filter(fn (Service $service): bool => $service->staff->contains('id', $staff->id))->values();
                $ranges = $this->workingRanges($staff->weekly_hours[$day->isoWeekday()] ?? [], $open, $day);
                if ($staffServices->isEmpty() || $ranges === []) {
                    continue;
                }
                foreach ($ranges as [$rangeStart, $rangeEnd]) {
                    $cursor = $rangeStart->addMinutes((($sequence + $day->dayOfYear) % 3) * 15);
                    while (true) {
                        $sequence++;
                        $service = $staffServices[$sequence % $staffServices->count()];
                        $busyUntil = $cursor->addMinutes($service->duration_minutes + $service->buffer_after);
                        if ($busyUntil->greaterThan($rangeEnd)) {
                            break;
                        }
                        if ($sequence % 4 !== 0) {
                            $this->createBooking($staff, $service, $guests[($sequence * 7) % $guests->count()], $cursor, $sequence, $now);
                            $created++;
                        }
                        $cursor = $busyUntil->addMinutes(($sequence % 4) * 15);
                    }
                }
            }
        }

        return $created;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $staffRanges
     * @param  list<array{0: string, 1: string}>  $openRanges
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function workingRanges(array $staffRanges, array $openRanges, CarbonImmutable $day): array
    {
        $ranges = [];
        foreach ($staffRanges as $work) {
            foreach ($openRanges as $opening) {
                $start = max($work[0], $opening[0]);
                $end = min($work[1], $opening[1]);
                if ($start < $end) {
                    $ranges[] = [$day->setTimeFromTimeString($start), $day->setTimeFromTimeString($end)];
                }
            }
        }

        return $ranges;
    }

    private function createBooking(Staff $staff, Service $service, Guest $guest, CarbonImmutable $start, int $sequence, CarbonImmutable $now): void
    {
        $end = $start->addMinutes($service->duration_minutes);
        $busyUntil = $end->addMinutes($service->buffer_after);
        $status = match (true) {
            $end->greaterThan($now) => $sequence % 10 === 0 ? 'cancelled' : 'confirmed',
            default => ['completed', 'completed', 'completed', 'completed', 'completed', 'completed', 'no_show', 'cancelled'][$sequence % 8],
        };
        $token = bin2hex(random_bytes(32));
        $booking = Booking::create([
            'service_id' => $service->id, 'staff_id' => $staff->id, 'guest_id' => $guest->id, 'service_name' => $service->name,
            'duration_minutes' => $service->duration_minutes, 'price' => $service->price, 'price_kind' => $service->price_kind,
            'buffer_before' => 0, 'buffer_after' => $service->buffer_after,
            'starts_at' => $start->utc(), 'ends_at' => $end->utc(), 'busy_from' => $start->utc(), 'busy_until' => $busyUntil->utc(),
            'status' => $status, 'source' => ['online', 'online', 'admin', 'phone'][$sequence % 4],
            'request_key' => (string) Str::uuid(), 'request_hash' => hash('sha256', (string) Str::uuid()),
            'token_hash' => hash('sha256', $token), 'manage_token' => $token, 'token_expires_at' => $start->addDays(30)->utc(),
            'actual_amount' => $status === 'completed' ? $service->price : null, 'revision' => 1,
        ]);
        BookingEvent::create(['booking_id' => $booking->id, 'action' => 'created', 'changes' => []]);
    }
}
