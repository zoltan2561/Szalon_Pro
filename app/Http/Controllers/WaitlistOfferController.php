<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Services\SmsGateway;
use App\Services\WaitlistOffers;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaitlistOfferController extends Controller
{
    public function join(Request $request, WaitlistOffers $offers): View
    {
        $this->ensureEnabled();
        $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $services = Service::where('active', true)->with('staff')->orderBy('sort_order')->orderBy('name')->get();
        $service = $services->firstWhere('id', $request->integer('service_id')) ?? $services->first();
        $staffId = $request->integer('staff_id');
        if (! $service?->staff->contains('id', $staffId)) {
            $staffId = null;
        }
        $today = now(Salon::current()->timezone)->toDateString();
        $date = $request->query('date', $today);
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today) {
            $date = $today;
        }
        $date = min($date, now(Salon::current()->timezone)->addDays(Salon::current()->horizon_days)->toDateString());

        $phoneOnly = Salon::current()->featureEnabled('phone_booking') && Salon::current()->featureEnabled('sms') && app(SmsGateway::class)->configured();

        return view('public.waitlist-join', compact('services', 'service', 'staffId', 'date', 'phoneOnly') + ['automatic' => $offers->enabled()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        abort_unless($request->input('website') === null || $request->input('website') === '', 422);
        $salon = Salon::current();
        $phoneOnly = $salon->featureEnabled('phone_booking') && $salon->featureEnabled('sms') && app(SmsGateway::class)->configured();
        $today = now($salon->timezone)->toDateString();
        $data = $request->validate([
            'service_id' => 'required|integer|exists:services,id', 'staff_id' => 'nullable|integer|exists:staff,id',
            'preferred_date' => 'required|date_format:Y-m-d|after_or_equal:'.$today,
            'preferred_until' => 'required|date_format:Y-m-d|after_or_equal:preferred_date',
            'time_from' => 'nullable|required_with:time_until|date_format:H:i', 'time_until' => 'nullable|required_with:time_from|date_format:H:i|after:time_from',
            'name' => 'required|string|max:120', 'email' => ($phoneOnly ? 'nullable|required_without:phone' : 'required').'|email:rfc|max:200',
            'phone' => 'nullable|string|max:40', 'notify_consent' => 'accepted',
        ]);
        $from = CarbonImmutable::parse($data['preferred_date'], $salon->timezone);
        $until = CarbonImmutable::parse($data['preferred_until'], $salon->timezone);
        if ($from->diffInDays($until) > 30 || $until->gt(now($salon->timezone)->startOfDay()->addDays($salon->horizon_days))) {
            throw ValidationException::withMessages(['preferred_until' => 'Legfeljebb 31 napos időszakot válassz, a szalon foglalási időszakán belül.']);
        }
        $service = Service::where('active', true)->findOrFail($data['service_id']);
        if (! empty($data['staff_id']) && ! $service->staff()->whereKey($data['staff_id'])->where('active', true)->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'Ez a szakember nem végezheti a kiválasztott szolgáltatást.']);
        }
        if (! empty($data['phone']) && ! app(SmsGateway::class)->normalizeNumber($data['phone'])) {
            throw ValidationException::withMessages(['phone' => 'Érvényes, országkóddal megadható telefonszám szükséges.']);
        }
        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($data, $token, $until): void {
            Salon::whereKey(1)->increment('lock_version');
            $guest = Guest::create(['name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null]);
            WaitlistEntry::create(['guest_id' => $guest->id, 'service_id' => $data['service_id'], 'staff_id' => $data['staff_id'] ?? null, 'preferred_date' => $data['preferred_date'], 'preferred_until' => $data['preferred_until'], 'time_from' => $data['time_from'] ?? null, 'time_until' => $data['time_until'] ?? null, 'status' => 'waiting', 'token_hash' => hash('sha256', $token), 'manage_token' => $token, 'token_expires_at' => $until->addDays(7)->endOfDay()->utc(), 'notification_consent_at' => now()]);
        }, 5);

        return redirect()->route('waitlist.request', $token)->with('success', 'Felvettünk a várólistára. Ez még nem foglalás.');
    }

    public function request(string $token, WaitlistOffers $offers): View
    {
        $entry = $this->entry($token);

        return view('public.waitlist-request', compact('entry', 'token') + ['automatic' => $offers->enabled()]);
    }

    public function cancel(string $token): RedirectResponse
    {
        $entry = $this->entry($token);
        DB::transaction(function () use ($entry): void {
            Salon::whereKey(1)->increment('lock_version');
            WaitlistEntry::whereKey($entry->id)->where('status', 'waiting')->update(['status' => 'cancelled', 'notification_consent_at' => null]);
            WaitlistOffer::where('waitlist_entry_id', $entry->id)->whereIn('status', ['pending', 'retry', 'sent'])->update(['status' => 'obsolete']);
        }, 5);

        return back()->with('success', 'A várólistás kérést visszavontad. Meglévő foglalásod ettől nem változik.');
    }

    public function show(string $token, WaitlistOffers $offers): View
    {
        $offer = $this->offer($token);

        return view('public.waitlist-offer', compact('offer', 'token') + ['available' => $offers->valid($offer) && Salon::current()->featureEnabled('waitlist')]);
    }

    public function accept(string $token, Request $request, WaitlistOffers $offers): RedirectResponse
    {
        $request->validate(['terms' => 'accepted']);
        $booking = $offers->accept($this->offer($token));
        $request->session()->put('last_booking', $booking->id);

        return redirect()->route('booking.success');
    }

    private function entry(string $token): WaitlistEntry
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);

        return WaitlistEntry::with('service', 'staff')->where('token_hash', hash('sha256', $token))->where('token_expires_at', '>', now())->firstOrFail();
    }

    private function offer(string $token): WaitlistOffer
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);

        return WaitlistOffer::with('entry.guest', 'entry.service', 'staff', 'booking')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now()->subDay())->whereHas('entry.guest', fn ($query) => $query->whereNull('anonymized_at'))->firstOrFail();
    }

    private function ensureEnabled(): void
    {
        abort_unless(Salon::current()->published && Salon::current()->featureEnabled('waitlist'), 404);
    }
}
