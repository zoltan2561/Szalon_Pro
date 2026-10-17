<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Services\Availability;
use App\Services\BookingService;
use App\Services\Branding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicController extends Controller
{
    public function home(Request $request, Availability $availability, Branding $branding): View|RedirectResponse
    {
        $salon = Salon::current();
        abort_unless($salon->published || auth()->user()?->role === 'owner', 404);
        $requestedStyle = $request->query('stilus');
        $demoUrls = $branding->demoUrls();
        if ($salon->demo && is_string($requestedStyle) && isset($demoUrls[$requestedStyle])) {
            return redirect()->away($demoUrls[$requestedStyle]);
        }

        return view('public.home', ['salon' => $salon, 'services' => Service::where('active', true)->with('staff')->get(), 'staff' => Staff::where('active', true)->get(), 'ready' => $availability->ready()]);
    }

    public function book(Request $request, Availability $availability): View
    {
        $salon = Salon::current();
        abort_unless($salon->published || $request->user()?->role === 'owner', 404);
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $services = Service::where('active', true)->with('staff')->get();
        $requestedStaff = $request->integer('staff_id');
        $service = $services->firstWhere('id', (int) $request->query('service_id')) ?? ($requestedStaff ? $services->first(fn ($s) => $s->staff->contains($requestedStaff)) : null) ?? $services->first();
        $staffId = $request->integer('staff_id') ?: null;
        if ($staffId && ! $service?->staff->contains($staffId)) {
            $staffId = null;
        }
        $date = $request->filled('date') ? $request->query('date') : now($salon->timezone)->toDateString();
        $slots = $service ? $availability->slots($service, $date, $staffId) : [];
        if (! $request->filled('date') && $service && ! $slots) {
            $today = now($salon->timezone)->startOfDay();
            for ($daysAhead = 1; $daysAhead <= min($salon->horizon_days, 7); $daysAhead++) {
                $candidate = $today->copy()->addDays($daysAhead);
                if (! ($salon->opening_hours[$candidate->isoWeekday()] ?? [])) {
                    continue;
                }
                $date = $candidate->toDateString();
                $slots = $availability->slots($service, $date, $staffId);
                if ($slots) {
                    break;
                }
            }
        }

        return view('public.book', compact('salon', 'services', 'service', 'date', 'staffId', 'slots') + ['requestKey' => old('request_key', Str::uuid()->toString()), 'ready' => $availability->ready()]);
    }

    public function store(Request $request, BookingService $bookings): RedirectResponse
    {
        $data = $this->bookingData($request);
        $booking = $bookings->create($data);
        $request->session()->put('last_booking', $booking->id);

        return redirect()->route('booking.success');
    }

    public function success(Request $request): View
    {
        $booking = Booking::with('guest', 'staff')->findOrFail($request->session()->get('last_booking'));

        return view('public.success', compact('booking'));
    }

    public function manage(string $token, Request $request, Availability $availability): View
    {
        $booking = $this->byToken($token);
        $date = $request->query('date', $booking->starts_at->setTimezone(Salon::current()->timezone)->toDateString());
        $service = $booking->service;
        $service->duration_minutes = $booking->duration_minutes;
        $service->buffer_before = $booking->buffer_before;
        $service->buffer_after = $booking->buffer_after;

        return view('public.manage', compact('booking', 'token', 'date') + ['slots' => $availability->slots($service, $date, null, $booking)]);
    }

    public function update(string $token, Request $request, BookingService $bookings): RedirectResponse
    {
        $booking = $this->byToken($token);
        $data = $request->validate(['action' => ['required', Rule::in(['move', 'cancel'])], 'revision' => 'required|integer', 'slot' => 'required_if:action,move|string']);
        if ($data['action'] === 'move') {
            [$data['staff_id'],$data['starts_at']] = $this->splitSlot($data['slot']);
        }
        $bookings->change($booking, $data);

        return back()->with('success', 'A foglalást módosítottuk.');
    }

    public function bookingData(Request $request, bool $manual = false): array
    {
        $data = $request->validate(['service_id' => 'required|integer|exists:services,id', 'slot' => 'required|string', 'name' => ($manual && $request->filled('guest_id') ? 'nullable' : 'required').'|string|max:120',
            'email' => ($manual && $request->filled('guest_id') ? 'nullable' : 'required').'|email:rfc|max:200', 'phone' => 'nullable|string|max:40', 'request_key' => 'required|uuid',
            'guest_id' => $manual ? 'nullable|integer|exists:guests,id' : 'prohibited', 'terms' => $manual ? 'nullable' : 'accepted']);
        [$data['staff_id'],$data['starts_at']] = $this->splitSlot($data['slot']);

        return $data;
    }

    private function splitSlot(string $slot): array
    {
        $parts = explode('|', $slot, 2);
        abort_unless(count($parts) === 2 && ctype_digit($parts[0]), 422);

        return [(int) $parts[0], $parts[1]];
    }

    private function byToken(string $token): Booking
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);

        return Booking::where('token_hash', hash('sha256', $token))->where('token_expires_at', '>', now())->firstOrFail();
    }
}
