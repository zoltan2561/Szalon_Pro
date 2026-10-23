<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\Guest;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\Service;
use App\Models\SmsOutboxMessage;
use App\Models\Staff;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(private Availability $availability, private BenefitsService $benefits, private SmsNotificationSender $smsNotifications, private SmsGateway $smsGateway) {}

    public function create(array $data, ?User $actor = null, ?Guest $rebookingGuest = null): Booking
    {
        return DB::transaction(function () use ($data, $actor, $rebookingGuest): Booking {
            Salon::whereKey(1)->increment('lock_version');
            $fingerprint = hash('sha256', json_encode([$data['service_id'], $data['staff_id'], $data['starts_at'], $data['guest_id'] ?? $rebookingGuest?->id, $data['waitlist_entry_id'] ?? null, $data['name'] ?? null, $data['email'] ?? null, $data['phone'] ?? null, (bool) ($data['sms_opt_in'] ?? false), $actor?->id]));
            $key = ($actor ? 'admin:'.$actor->id.':' : 'public:').$data['request_key'];
            if ($old = Booking::where('request_key', $key)->first()) {
                if (! hash_equals($old->request_hash, $fingerprint)) {
                    $this->error('Ez a kérésazonosító már más foglaláshoz tartozik.');
                }

                return $old;
            }
            $salon = Salon::current();
            if (! $actor && (! $salon->published || ! $this->availability->ready())) {
                $this->error('Az online foglalás jelenleg nem elérhető.');
            }
            if ($actor && $actor->role !== 'owner') {
                abort_unless($actor->can_manage_bookings && (int) $actor->staff_id === (int) $data['staff_id'], 403);
            }
            abort_if($actor && $rebookingGuest, 403);
            $service = Service::findOrFail($data['service_id']);
            $staff = Staff::findOrFail($data['staff_id']);
            $waitlistEntry = null;
            if (! empty($data['waitlist_entry_id'])) {
                abort_unless($actor && $salon->featureEnabled('waitlist'), 403);
                $waitlistEntry = WaitlistEntry::lockForUpdate()->findOrFail($data['waitlist_entry_id']);
                if ($waitlistEntry->status !== 'waiting' || (int) $waitlistEntry->guest_id !== (int) ($data['guest_id'] ?? 0)
                    || (int) $waitlistEntry->service_id !== (int) $service->id
                    || ($waitlistEntry->staff_id && (int) $waitlistEntry->staff_id !== (int) $staff->id)) {
                    throw ValidationException::withMessages(['waitlist_entry_id' => 'A várólistás kérés már nem érvényes ehhez a foglaláshoz.']);
                }
                abort_unless($actor->role === 'owner' || (int) $waitlistEntry->staff_id === (int) $actor->staff_id, 403);
            }
            $start = $this->availability->parse($data['starts_at']);
            if (! $this->availability->canBook($service, $staff, $start)) {
                $this->error('Ez az időpont már nem szabad. Válassz másikat; az adataid megmaradtak.');
            }
            if ($rebookingGuest) {
                $guest = Guest::findOrFail($rebookingGuest->id);
                abort_if($guest->anonymized_at || ! $guest->email || mb_strtolower((string) ($data['email'] ?? '')) !== mb_strtolower($guest->email), 422);
            } elseif (! empty($data['guest_id']) && $actor) {
                $guest = Guest::findOrFail($data['guest_id']);
                abort_unless($actor->role === 'owner' || $guest->bookings()->visibleTo($actor)->exists() || $waitlistEntry?->staff_id === $actor->staff_id, 403);
                abort_if($guest->anonymized_at, 422);
            } else {
                $guest = Guest::create(['name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null]);
            }
            if ($salon->featureEnabled('sms') && ($data['sms_opt_in'] ?? false) && ! $this->smsGateway->normalizeNumber($guest->phone)) {
                throw ValidationException::withMessages(['phone' => 'SMS-emlékeztetőhöz érvényes, országkóddal megadható telefonszám szükséges.']);
            }
            $token = bin2hex(random_bytes(32));
            $booking = Booking::create([
                'service_id' => $service->id, 'staff_id' => $staff->id, 'guest_id' => $guest->id, 'status' => 'confirmed', 'revision' => 1,
                'service_name' => $service->name, 'duration_minutes' => $service->duration_minutes, 'price' => $service->price, 'price_kind' => $service->price_kind,
                'buffer_before' => $service->buffer_before, 'buffer_after' => $service->buffer_after,
                'starts_at' => $start, 'ends_at' => $start->addMinutes($service->duration_minutes),
                'busy_from' => $start->subMinutes($service->buffer_before), 'busy_until' => $start->addMinutes($service->duration_minutes + $service->buffer_after),
                'source' => $actor ? 'manual' : 'online', 'sms_opt_in' => $salon->featureEnabled('sms') && (bool) ($data['sms_opt_in'] ?? false), 'request_key' => $key, 'request_hash' => $fingerprint,
                'token_hash' => hash('sha256', $token), 'manage_token' => $token, 'token_expires_at' => $start->addDays(30),
            ]);
            $this->event($booking, 'created', [], $actor);
            $this->notify($booking, 'confirmation');
            $this->smsNotifications->queue($booking, 'confirmation');
            $waitlistEntry?->update(['status' => 'booked', 'booked_booking_id' => $booking->id]);

            return $booking;
        }, 5);
    }

    public function change(Booking $booking, array $data, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $data, $actor): Booking {
            Salon::whereKey(1)->increment('lock_version');
            $booking = Booking::findOrFail($booking->id);
            if ($actor) {
                abort_unless($actor->role === 'owner' || ($actor->can_manage_bookings && $actor->staff_id === $booking->staff_id), 403);
            }
            if ($booking->revision !== (int) $data['revision']) {
                $this->error('A foglalás időközben változott. Nyisd meg újra.');
            }
            if ($booking->status !== 'confirmed') {
                $this->error('Ez a foglalás már lezárt.');
            }
            if (! $actor && CarbonImmutable::now()->gte($booking->starts_at->subHours(Salon::current()->cancel_notice_hours))) {
                $this->error('Az online módosítási határidő lejárt. Kérjük, keresd a szalont.');
            }
            $before = $booking->only(['starts_at', 'ends_at', 'staff_id', 'status', 'actual_amount']);
            if ($data['action'] === 'move') {
                $service = $booking->service->replicate();
                $service->id = $booking->service_id;
                $service->duration_minutes = $booking->duration_minutes;
                $service->buffer_before = $booking->buffer_before;
                $service->buffer_after = $booking->buffer_after;
                $start = $this->availability->parse($data['starts_at']);
                $staff = Staff::findOrFail($data['staff_id']);
                if ($actor && $actor->role !== 'owner') {
                    abort_unless($actor->staff_id === $staff->id, 403);
                }
                if (! $this->availability->canBook($service, $staff, $start, $booking)) {
                    $this->error('Az új időpont nem szabad. Az eredeti foglalás érvényben maradt.');
                }
                $booking->fill(['staff_id' => $staff->id, 'starts_at' => $start, 'ends_at' => $start->addMinutes($booking->duration_minutes),
                    'busy_from' => $start->subMinutes($booking->buffer_before), 'busy_until' => $start->addMinutes($booking->duration_minutes + $booking->buffer_after),
                    'token_expires_at' => $start->addDays(30)]);
            } else {
                $status = ['cancel' => 'cancelled', 'complete' => 'completed', 'no_show' => 'no_show'][$data['action']] ?? null;
                abort_unless($status && ($actor || $status === 'cancelled'), 403);
                if (in_array($status, ['completed', 'no_show']) && $booking->starts_at->isFuture()) {
                    $this->error('Jövőbeli látogatás még nem zárható le.');
                }
                $booking->status = $status;
                if ($status === 'completed') {
                    $booking->actual_amount = Salon::current()->featureEnabled('financials') ? ($data['actual_amount'] ?? $booking->price) : null;
                }
            }
            $booking->revision++;
            $booking->save();
            if ($data['action'] === 'complete') {
                $this->benefits->completeBooking($booking, $data['guest_pass_id'] ?? null);
            }
            $this->event($booking, $data['action'], ['before' => $before, 'after' => $booking->only(array_keys($before))], $actor);
            OutboxMessage::where('booking_id', $booking->id)->whereIn('status', ['pending', 'retry', 'failed'])->update(['status' => 'obsolete']);
            SmsOutboxMessage::where('booking_id', $booking->id)->whereIn('status', ['pending', 'retry', 'failed'])->update(['status' => 'obsolete']);
            if (in_array($data['action'], ['move', 'cancel'])) {
                $this->notify($booking, $data['action'] === 'move' ? 'changed' : 'cancelled');
                if ($data['action'] === 'move') {
                    $this->smsNotifications->queue($booking, 'changed');
                }
            }

            return $booking;
        }, 5);
    }

    public function notify(Booking $booking, string $kind): void
    {
        if (! $booking->guest->email) {
            return;
        }
        OutboxMessage::firstOrCreate(['booking_id' => $booking->id, 'revision' => $booking->revision, 'kind' => $kind], ['scheduled_at' => now()]);
        $due = $booking->starts_at->subHours(Salon::current()->reminder_hours);
        if ($booking->status === 'confirmed' && Salon::current()->reminder_hours > 0 && $due->isFuture()) {
            OutboxMessage::firstOrCreate(['booking_id' => $booking->id, 'revision' => $booking->revision, 'kind' => 'reminder'], ['scheduled_at' => $due]);
        }
    }

    private function event(Booking $booking, string $action, array $changes, ?User $actor): void
    {
        BookingEvent::create(['booking_id' => $booking->id, 'actor_id' => $actor?->id, 'action' => $action, 'changes' => $changes]);
    }

    private function error(string $message): never
    {
        throw ValidationException::withMessages(['starts_at' => $message]);
    }
}
