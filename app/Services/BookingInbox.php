<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\User;
use App\Notifications\NewBookingArrived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

class BookingInbox
{
    public function record(Booking $booking): void
    {
        User::where('is_demo_viewer', false)->where(function (Builder $query) use ($booking): void {
            $query->where('role', 'owner')->orWhere(fn (Builder $staff) => $staff->where('role', 'staff')->where('staff_id', $booking->staff_id));
        })->each(function (User $user) use ($booking): void {
            $user->notify(new NewBookingArrived($booking->id));
        });
        if ($this->businessRecipients($booking) !== []) {
            OutboxMessage::firstOrCreate(['booking_id' => $booking->id, 'revision' => $booking->revision, 'kind' => 'new_booking'], ['scheduled_at' => now()]);
        }
    }

    /** @return list<string> */
    public function businessRecipients(Booking $booking): array
    {
        return collect([Salon::current()->email])->merge(User::where('staff_id', $booking->staff_id)->where('is_demo_viewer', false)->pluck('email'))
            ->map(fn (?string $email): string => mb_strtolower(trim($email ?? '')))
            ->filter(fn (string $email): bool => (bool) filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
    }

    /** @return array{count: int, through_id: int, items: list<array{id: string, unread: bool, name: string, when: string, staff: string, service: string, status: string}>} */
    public function summary(User $user): array
    {
        if ($user->isDemoViewer()) {
            return ['count' => 0, 'through_id' => 0, 'items' => []];
        }
        $throughId = (int) Booking::visibleTo($user)->max('id');
        $query = $this->visible($user)->where('data->booking_id', '<=', $throughId);
        $count = (clone $query)->whereNull('read_at')->count();
        $notifications = $query->reorder()->orderByRaw('read_at IS NOT NULL')->latest()->orderByDesc('id')->limit(8)->get();
        $bookings = Booking::visibleTo($user)->with('guest', 'staff')->whereKey($notifications->pluck('data.booking_id'))->get()->keyBy('id');
        $items = $notifications->flatMap(function (DatabaseNotification $notification) use ($bookings): array {
            $booking = $bookings->get($notification->data['booking_id']);
            if (! $booking) {
                return [];
            }

            return [['id' => $notification->id, 'unread' => $notification->read_at === null, 'name' => $booking->guest->name, 'when' => $booking->starts_at->setTimezone(Salon::current()->timezone)->locale('hu')->isoFormat('YYYY. MMM D., dddd HH:mm'), 'staff' => $booking->staff->name, 'service' => $booking->service_name, 'status' => $booking->label()]];
        })->all();

        return ['count' => $count, 'through_id' => $throughId, 'items' => $items];
    }

    public function open(User $user, string $notificationId): Booking
    {
        $notification = $this->visible($user)->whereKey($notificationId)->firstOrFail();
        $booking = Booking::visibleTo($user)->findOrFail($notification->data['booking_id']);
        $notification->markAsRead();

        return $booking;
    }

    public function markRead(User $user, int $throughId): void
    {
        $this->visible($user)->whereNull('read_at')->where('data->booking_id', '<=', $throughId)->update(['read_at' => now()]);
    }

    /** @return Builder<DatabaseNotification> */
    private function visible(User $user): Builder
    {
        return $user->notifications()->getQuery()->where('type', NewBookingArrived::class)->whereIn('data->booking_id', Booking::visibleTo($user)->select('id'));
    }
}
