<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Salon;
use App\Services\BookingReorganization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingReorganizationController extends Controller
{
    public function options(Booking $booking, Request $request, BookingReorganization $schedule): JsonResponse
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d']);
        $visits = $schedule->candidates($booking, $request->user(), $data['date']);

        return response()->json(['options' => $visits->map(fn (Booking $visit): array => ['id' => $visit->id, 'time' => $visit->starts_at->setTimezone(Salon::current()->timezone)->format('H:i'), 'name' => $visit->guest->name, 'description' => $visit->service_name.' · '.$visit->duration_minutes.' perc · '.$visit->staff->name])])->header('Cache-Control', 'private, no-store');
    }

    public function preview(Booking $booking, Request $request, BookingReorganization $schedule): View
    {
        $schedule->authorize($booking, $request->user());
        $input = $request->validate(['revision' => 'required|integer|min:1', 'mode' => ['required', Rule::in(['shift_day', 'swap'])], 'shift_minutes' => 'nullable|required_if:mode,shift_day|integer|between:-720,720', 'swap_booking_id' => 'nullable|required_if:mode,swap|integer|exists:bookings,id']);
        $plan = $schedule->plan($booking, $input, $request->user());

        return view('admin.booking-reorganization-preview', compact('booking', 'plan') + ['planToken' => $plan['available'] ? $schedule->token($booking, $input, $plan, $request->user()) : null]);
    }

    public function commit(Booking $booking, Request $request, BookingReorganization $schedule): RedirectResponse
    {
        $data = $request->validate(['plan_token' => 'required|string|max:20000']);
        $count = $schedule->commit($booking, $data['plan_token'], $request->user());

        return redirect()->route('admin.booking', $booking)->with('success', $count.' foglalás új időpontját elmentettük.');
    }
}
