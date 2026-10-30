<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\SmsOutboxMessage;
use App\Models\Staff;
use App\Services\Availability;
use App\Services\BookingLinks;
use App\Services\BookingSeries;
use App\Services\BookingService;
use App\Services\Branding;
use App\Services\ServiceSelection;
use App\Services\SmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        return view('public.home', ['salon' => $salon, 'services' => Service::where('active', true)->with('staff')->orderBy('sort_order')->orderBy('id')->get(), 'staff' => Staff::where('active', true)->orderBy('sort_order')->orderBy('id')->get(), 'ready' => $availability->ready()]);
    }

    public function book(Request $request, Availability $availability, SmsGateway $smsGateway): View
    {
        $salon = Salon::current();
        abort_unless($salon->published || $request->user()?->role === 'owner', 404);
        $request->validate(['date' => 'nullable|date_format:Y-m-d', 'extra_service_ids' => 'nullable|array|max:4', 'extra_service_ids.*' => 'integer|distinct|exists:services,id']);
        $services = Service::where('active', true)->with('staff')->orderBy('sort_order')->orderBy('id')->get();
        $requestedStaff = $request->integer('staff_id');
        $service = $services->firstWhere('id', (int) $request->query('service_id')) ?? ($requestedStaff ? $services->first(fn ($s) => $s->staff->contains($requestedStaff)) : null) ?? $services->first();
        if ($service) {
            $service = app(ServiceSelection::class)->combine($service, $request->input('extra_service_ids', []));
        }
        $staffId = $request->integer('staff_id') ?: null;
        if ($staffId && ! $service?->staff->contains($staffId)) {
            $staffId = null;
        }
        $date = $request->filled('date') ? $request->query('date') : now($salon->timezone)->toDateString();
        $slots = $service ? $availability->slots($service, $date, $staffId) : [];
        if (! $request->filled('date') && $service && ! $slots) {
            $today = CarbonImmutable::now($salon->timezone)->startOfDay();
            $next = $availability->firstAvailableDay($service, $today->addDay(), $today->addDays($salon->horizon_days), $staffId);
            if ($next) {
                $date = $next['date'];
                $slots = $next['slots'];
            }
        }

        $nextAvailable = null;
        $nextAvailableUrl = null;
        if ($service && ! $slots && $request->filled('date')) {
            $today = CarbonImmutable::now($salon->timezone)->startOfDay();
            $searchStart = CarbonImmutable::parse($date, $salon->timezone)->addDay()->max($today);
            $searchEnd = $today->addDays($salon->horizon_days);
            if ($searchStart->lte($searchEnd)) {
                $nextAvailable = $availability->firstAvailableDay($service, $searchStart, $searchEnd, $staffId);
            }
            if ($nextAvailable) {
                $parameters = ['service_id' => $service->id, 'staff_id' => $staffId, 'date' => $nextAvailable['date'], 'extra_service_ids' => $request->input('extra_service_ids', []), 'custom_services' => 1];
                if ($salon->featureEnabled('sharing') && in_array($request->query('utm_source'), BookingLinks::Sources, true)) {
                    $parameters['utm_source'] = $request->query('utm_source');
                }
                $rebookToken = $request->attributes->get('rebookToken');
                $nextAvailableUrl = $rebookToken
                    ? route('booking.rebook.form', ['token' => $rebookToken] + $parameters)
                    : route('booking.form', $parameters);
            }
        }

        return view('public.book', compact('salon', 'services', 'service', 'date', 'staffId', 'slots', 'nextAvailable', 'nextAvailableUrl') + ['requestKey' => old('request_key', Str::uuid()->toString()), 'ready' => $availability->ready(), 'rebookToken' => $request->attributes->get('rebookToken'), 'rebookingGuest' => $request->attributes->get('rebookingGuest'), 'smsReady' => $salon->featureEnabled('sms') && $smsGateway->configured()]);
    }

    public function store(Request $request, BookingService $bookings): RedirectResponse
    {
        $this->rejectAutomatedBooking($request);
        $data = $this->bookingData($request);
        $booking = $bookings->create($data);
        $request->session()->put('last_booking', $booking->id);

        return redirect()->route('booking.success');
    }

    public function rebook(string $token, Request $request, Availability $availability, SmsGateway $smsGateway): View|RedirectResponse
    {
        $booking = $this->rebookingSource($token);
        if (! $booking) {
            return redirect()->route('booking.form')->with('error', 'Az újrafoglalási link lejárt. Kérj új időpontot, vagy keresd a szalont.');
        }
        $request->query->set('service_id', $request->query('service_id', $booking->service_id));
        $request->query->set('staff_id', $request->query('staff_id', $booking->staff_id));
        if (! $request->boolean('custom_services') && ! $request->has('extra_service_ids')) {
            $request->query->set('extra_service_ids', $booking->rebookingParameters()['extra_service_ids']);
        }
        $request->attributes->set('rebookToken', $token);
        $request->attributes->set('rebookingGuest', $booking->guest);

        return $this->book($request, $availability, $smsGateway);
    }

    public function storeRebooking(string $token, Request $request, BookingService $bookings): RedirectResponse
    {
        $this->rejectAutomatedBooking($request);
        $source = $this->rebookingSource($token);
        if (! $source) {
            return redirect()->route('booking.form')->with('error', 'Az újrafoglalási link lejárt. Kérj új időpontot, vagy keresd a szalont.');
        }
        $data = $this->bookingData($request);
        if (mb_strtolower(trim((string) ($data['email'] ?? ''))) !== mb_strtolower((string) $source->guest->email)
            || trim($data['name']) !== $source->guest->name || (! $source->guest->email && ($data['phone'] ?? null) !== $source->guest->phone)) {
            throw ValidationException::withMessages(['email' => 'A korábbi vendégadatok megváltoztak. Kérjük, keresd a szalont.']);
        }
        $booking = $bookings->create($data, null, $source->guest);
        $request->session()->put('last_booking', $booking->id);

        return redirect()->route('booking.success');
    }

    public function success(Request $request): View
    {
        $booking = Booking::with('guest', 'staff')->findOrFail($request->session()->get('last_booking'));
        $confirmationChannel = $booking->guest->email ? 'email' : (SmsOutboxMessage::where('booking_id', $booking->id)->where('kind', 'confirmation')->exists() ? 'sms' : null);
        $seriesBookings = $booking->series_key ? Booking::with('staff')->where('series_key', $booking->series_key)->where('guest_id', $booking->guest_id)->orderBy('starts_at')->get() : collect();

        return view('public.success', compact('booking', 'confirmationChannel', 'seriesBookings'));
    }

    public function manage(string $token, Request $request, Availability $availability): View
    {
        $booking = $this->byToken($token);
        $salon = Salon::current();
        $service = app(ServiceSelection::class)->forBooking($booking);
        $staff = $service->staff->where('active', true)->sortBy('sort_order')->values();
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'staff_id' => ['nullable', 'integer', Rule::in($staff->modelKeys())],
        ]);
        $staffId = $request->has('staff_id') ? ($request->integer('staff_id') ?: null) : $booking->staff_id;
        $date = $request->query('date') ?: $booking->starts_at->setTimezone($salon->timezone)->toDateString();
        $slots = $availability->slots($service, $date, $staffId, $booking);
        $nextAvailable = null;
        $nextAvailableUrl = null;
        if (! $slots && $booking->status === 'confirmed' && now()->lt($booking->starts_at->subHours($salon->cancel_notice_hours))) {
            $today = CarbonImmutable::now($salon->timezone)->startOfDay();
            $searchStart = CarbonImmutable::parse($date, $salon->timezone)->addDay()->max($today);
            $searchEnd = $today->addDays($salon->horizon_days);
            if ($searchStart->lte($searchEnd)) {
                $nextAvailable = $availability->firstAvailableDay($service, $searchStart, $searchEnd, $staffId, $booking);
            }
            if ($nextAvailable) {
                $nextAvailableUrl = route('booking.manage', ['token' => $token, 'date' => $nextAvailable['date'], 'staff_id' => $staffId ?? '']);
            }
        }

        $seriesBookings = $booking->series_key ? Booking::with('staff')->where('series_key', $booking->series_key)->where('guest_id', $booking->guest_id)->orderBy('starts_at')->get() : collect();

        return view('public.manage', compact('booking', 'token', 'date', 'seriesBookings', 'service', 'staff', 'staffId', 'slots', 'nextAvailable', 'nextAvailableUrl'));
    }

    public function update(string $token, Request $request, BookingService $bookings): RedirectResponse
    {
        $booking = $this->byToken($token);
        $data = $request->validate(['action' => ['required', Rule::in(['move', 'cancel'])], 'revision' => 'required|integer', 'slot' => 'required_if:action,move|string', 'repeat_scope' => ['nullable', Rule::in(['single', 'following'])]]);
        if ($data['action'] === 'move') {
            [$data['staff_id'],$data['starts_at']] = $this->splitSlot($data['slot']);
        }
        if (($data['repeat_scope'] ?? 'single') === 'following') {
            app(BookingSeries::class)->changeFollowing($booking, $data);
        } else {
            $bookings->change($booking, $data);
        }

        return back()->with('success', $data['action'] === 'cancel' ? 'A foglalásodat lemondtad. Várunk egy másik időpontban!' : 'A foglalásodat áthelyeztük. Az új időpontot alább találod.');
    }

    public function bookingData(Request $request, bool $manual = false): array
    {
        $mode = Salon::current()->featureEnabled('phone_booking') ? Salon::current()->contact_mode : 'email';
        $reviewConsent = $request->validate(['review_opt_in' => 'sometimes|boolean']);
        $emailRequired = ! $manual && in_array($mode, ['email', 'both'], true);
        $phoneRequired = ! $manual && in_array($mode, ['phone', 'both'], true);
        $data = $request->validate(['service_id' => 'required|integer|exists:services,id', 'slot' => 'required|string', 'name' => ($manual && $request->filled('guest_id') ? 'nullable' : 'required').'|string|max:120',
            'repeat_count' => 'nullable|integer|between:1,6', 'repeat_weeks' => ['required_if:repeat_count,2,3,4,5,6', 'nullable', Rule::in([1, 2, 3, 4, 6, 8])],
            'extra_service_ids' => 'nullable|array|max:4', 'extra_service_ids.*' => 'integer|distinct|exists:services,id',
            'email' => ($emailRequired ? 'required' : 'nullable').'|email:rfc|max:200', 'phone' => ($phoneRequired ? 'required' : 'nullable').'|string|max:40', 'request_key' => 'required|uuid',
            'guest_id' => $manual ? 'nullable|integer|exists:guests,id' : 'prohibited', 'waitlist_entry_id' => $manual ? 'nullable|integer|exists:waitlist_entries,id' : 'prohibited', 'sms_opt_in' => 'sometimes|boolean', 'terms' => $manual ? 'nullable' : 'accepted']);
        if (($phoneRequired || filled($data['phone'] ?? null)) && ! app(SmsGateway::class)->normalizeNumber($data['phone'] ?? null)) {
            throw ValidationException::withMessages(['phone' => 'Érvényes, országkóddal megadható telefonszám szükséges.']);
        }
        $data['referral_source'] = Salon::current()->featureEnabled('sharing')
            ? ($request->validate(['referral_source' => ['nullable', Rule::in(BookingLinks::Sources)]])['referral_source'] ?? null) : null;
        [$data['staff_id'],$data['starts_at']] = $this->splitSlot($data['slot']);

        return $data + ['review_opt_in' => Salon::current()->featureEnabled('reviews') && (bool) ($reviewConsent['review_opt_in'] ?? false)];
    }

    private function rejectAutomatedBooking(Request $request): void
    {
        $website = $request->input('website');
        abort_unless($website === null || $website === '', 422);
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

    private function rebookingSource(string $token): ?Booking
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return Booking::with('guest')->where('token_hash', hash('sha256', $token))->where('token_expires_at', '>', now())->whereHas('guest', fn ($query) => $query->whereNull('anonymized_at')->where(fn ($contact) => $contact->whereNotNull('email')->when(Salon::current()->featureEnabled('phone_booking'), fn ($phone) => $phone->orWhereNotNull('phone_normalized'))))->first();
    }
}
