<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\GoogleCalendarBusy;
use App\Models\GoogleCalendarConnection;
use App\Models\GoogleCalendarEvent;
use App\Models\Salon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class GoogleCalendarSync
{
    public function __construct(private GoogleCalendarClient $client) {}

    public function sync(GoogleCalendarConnection $connection): void
    {
        $lock = Cache::lock('google-calendar-sync-'.$connection->id, 600);
        if (! $lock->get()) {
            throw new RuntimeException('google_sync_running');
        }

        try {
            $this->exportBookings($connection);
            $connection->update(['last_exported_at' => now()]);
            $this->importBusy($connection);
        } catch (Throwable $exception) {
            $code = $exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'google_') ? $exception->getMessage() : 'google_sync_failed';
            $connection->update(['sync_status' => $code === 'google_reconnect_required' ? 'reconnect' : 'error', 'last_error_code' => mb_substr($code, 0, 100)]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function exportBookings(GoogleCalendarConnection $connection): void
    {
        foreach ($connection->events()->with('booking')->get() as $mapping) {
            if ($mapping->booking->staff_id !== $connection->staff_id || $mapping->booking->status === 'cancelled') {
                $this->client->deleteBooking($connection, $mapping->event_id);
                $mapping->delete();
            }
        }

        Booking::where('staff_id', $connection->staff_id)->where('status', 'confirmed')
            ->where('starts_at', '>=', now()->subDay())->orderBy('id')->chunkById(100, function ($bookings) use ($connection): void {
                foreach ($bookings as $booking) {
                    $mapping = GoogleCalendarEvent::firstOrCreate(
                        ['google_calendar_connection_id' => $connection->id, 'booking_id' => $booking->id],
                        ['event_id' => $this->client->eventId($connection, $booking)]
                    );
                    if ($mapping->synced_revision === $booking->revision) {
                        continue;
                    }
                    $this->client->saveBooking($connection, $booking, $mapping->event_id, $mapping->synced_revision === null);
                    $mapping->update(['synced_revision' => $booking->revision]);
                }
            });
    }

    private function importBusy(GoogleCalendarConnection $connection): void
    {
        $salon = Salon::current();
        $from = CarbonImmutable::now($salon->timezone)->startOfDay();
        $until = $from->addDays($salon->horizon_days + 2);
        $ownMappings = $connection->events()->with('booking')->get();
        $ownIds = $ownMappings->pluck('event_id')->flip();
        $remoteOwnEvents = [];
        $rows = [];
        foreach ($this->client->listEvents($connection, $from, $until) as $event) {
            if (! is_array($event) || ! is_string($event['id'] ?? null) || $event['id'] === '') {
                throw new RuntimeException('google_invalid_events');
            }
            if (isset($ownIds[$event['id']])) {
                $remoteOwnEvents[$event['id']] = $event;

                continue;
            }
            if (($event['status'] ?? null) === 'cancelled' || ($event['transparency'] ?? 'opaque') === 'transparent') {
                continue;
            }
            [$start, $end] = $this->eventInterval($event, $salon->timezone);
            if ($end->lte($start)) {
                throw new RuntimeException('google_invalid_events');
            }
            $rows[] = [
                'google_calendar_connection_id' => $connection->id,
                'event_key' => hash('sha256', $event['id']),
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach ($ownMappings as $mapping) {
            $booking = $mapping->booking;
            if (! $booking || $booking->status !== 'confirmed' || $booking->staff_id !== $connection->staff_id
                || $booking->busy_from->gte($until) || $booking->busy_until->lte($from)) {
                continue;
            }
            $remoteEvent = $remoteOwnEvents[$mapping->event_id] ?? null;
            if (! $remoteEvent || ! $this->matchesBooking($remoteEvent, $booking, $salon->timezone)) {
                $this->client->saveBooking($connection, $booking, $mapping->event_id, false);
            }
        }
        DB::transaction(function () use ($connection, $rows): void {
            GoogleCalendarBusy::where('google_calendar_connection_id', $connection->id)->delete();
            foreach (array_chunk($rows, 100) as $chunk) {
                GoogleCalendarBusy::insert($chunk);
            }
            $connection->update(['last_imported_at' => now(), 'sync_status' => 'connected', 'last_error_code' => null]);
        });
    }

    /** @param array<string, mixed> $event */
    private function matchesBooking(array $event, Booking $booking, string $timezone): bool
    {
        if (($event['status'] ?? null) === 'cancelled' || ($event['transparency'] ?? 'opaque') !== 'opaque') {
            return false;
        }
        try {
            [$start, $end] = $this->eventInterval($event, $timezone);
        } catch (RuntimeException) {
            return false;
        }

        return $start->getTimestamp() === $booking->busy_from->getTimestamp()
            && $end->getTimestamp() === $booking->busy_until->getTimestamp();
    }

    /** @param array<string, mixed> $event
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function eventInterval(array $event, string $timezone): array
    {
        $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
        $end = $event['end']['dateTime'] ?? $event['end']['date'] ?? null;
        if (! is_string($start) || ! is_string($end)) {
            throw new RuntimeException('google_invalid_events');
        }
        try {
            return [CarbonImmutable::parse($start, $timezone), CarbonImmutable::parse($end, $timezone)];
        } catch (Throwable) {
            throw new RuntimeException('google_invalid_events');
        }
    }
}
