<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BookingCalendarController extends Controller
{
    public function guest(string $token, Request $request, BookingCalendar $calendar): Response
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);
        $booking = Booking::with('staff')->where('token_hash', hash('sha256', $token))->where('token_expires_at', '>', now())->firstOrFail();

        return $this->download($booking, $calendar, $request);
    }

    public function admin(Booking $booking, Request $request, BookingCalendar $calendar): Response
    {
        abort_unless(Booking::visibleTo($request->user())->whereKey($booking->id)->exists(), 403);

        return $this->download($booking, $calendar, $request);
    }

    private function download(Booking $booking, BookingCalendar $calendar, Request $request): Response
    {
        $visits = collect([$booking]);
        if ($booking->series_key && $request->boolean('series')) {
            $visits = Booking::with('staff')->where('series_key', $booking->series_key)->where('guest_id', $booking->guest_id)
                ->when($request->routeIs('admin.*'), fn ($query) => $query->visibleTo($request->user()))->orderBy('starts_at')->get();
        }

        return response($calendar->contentMany($visits), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="szalonpro-idopont.ics"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
