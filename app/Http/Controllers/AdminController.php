<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\GoogleCalendarBusy;
use App\Models\Guest;
use App\Models\GuestPass;
use App\Models\LoyaltyEntry;
use App\Models\OutboxMessage;
use App\Models\PassUse;
use App\Models\Salon;
use App\Models\Service;
use App\Models\SmsOutboxMessage;
use App\Models\Staff;
use App\Models\TimeBlock;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WorkException;
use App\Services\Availability;
use App\Services\BenefitsService;
use App\Services\BookingService;
use App\Services\Branding;
use App\Services\OperationsHealth;
use App\Services\SmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function today(Request $request): View
    {
        $date = $request->query('date', now(Salon::current()->timezone)->toDateString());
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, Salon::current()->timezone);
        } catch (\Throwable) {
            abort(422);
        }
        abort_unless($day && $day->format('Y-m-d') === $date, 422);
        $week = $request->query('view') === 'week';
        if ($week) {
            $day = $day->startOfWeek(CarbonImmutable::MONDAY);
        }
        $end = $week ? $day->addDays(7) : $day->addDay();
        $bookings = Booking::visibleTo($request->user())->with(['guest' => fn ($query) => $query->withCount(['bookings as no_show_count' => fn ($bookings) => $bookings->visibleTo($request->user())->where('status', 'no_show')]), 'staff'])->where('starts_at', '>=', $day->utc())->where('starts_at', '<', $end->utc())->orderBy('starts_at')->get();
        $next = Booking::visibleTo($request->user())->with('guest', 'staff')->where('status', 'confirmed')->where('starts_at', '>=', now())->orderBy('starts_at')->first();
        $visibleStaffIds = $request->user()->role === 'owner' ? Staff::pluck('id')->all() : [$request->user()->staff_id];
        $calendarStaff = Staff::whereIn('id', $visibleStaffIds)->orderBy('name')->get();
        $blocks = TimeBlock::with('staff')->whereIn('staff_id', $visibleStaffIds)->where('starts_at', '<', $end->utc())->where('ends_at', '>', $day->utc())->orderBy('starts_at')->get();
        $exceptions = WorkException::with('staff')->whereIn('staff_id', $visibleStaffIds)->where('date', '>=', $day->toDateString())->where('date', '<', $end->toDateString())->orderBy('date')->get();
        $googleBusy = GoogleCalendarBusy::with('connection.staff')->whereHas('connection', fn ($query) => $query->whereIn('staff_id', $visibleStaffIds))->where('starts_at', '<', $end->utc())->where('ends_at', '>', $day->utc())->orderBy('starts_at')->get();

        return view('admin.calendar', compact('bookings', 'next', 'date', 'week', 'day', 'blocks', 'exceptions', 'googleBusy', 'calendarStaff') + ['failed' => $request->user()->role === 'owner' ? OutboxMessage::whereIn('status', ['failed', 'retry'])->count() + SmsOutboxMessage::whereIn('status', ['failed', 'retry'])->count() : 0]);
    }

    public function create(Request $request, Availability $availability, SmsGateway $smsGateway): View
    {
        $services = Service::where('active', true)->when($request->user()->role !== 'owner', fn ($q) => $q->whereHas('staff', fn ($q) => $q->where('staff.id', $request->user()->staff_id)))->with('staff')->get();
        $service = $services->firstWhere('id', $request->integer('service_id')) ?? $services->first();
        $date = $request->query('date', now(Salon::current()->timezone)->addDay()->toDateString());
        $staffId = $request->user()->role === 'owner' ? ($request->integer('staff_id') ?: null) : $request->user()->staff_id;
        if ($staffId && ! $service?->staff->contains($staffId)) {
            $staffId = null;
        }
        $guests = $this->guestQuery($request)->whereNull('anonymized_at')->orderBy('name')->get();
        if (Salon::current()->featureEnabled('waitlist') && $request->integer('waitlist_entry_id')) {
            $entry = WaitlistEntry::visibleTo($request->user())->whereKey($request->integer('waitlist_entry_id'))->where('status', 'waiting')->first();
            if ($entry && ($request->user()->role === 'owner' || $request->user()->can_manage_bookings) && ! $guests->contains('id', $entry->guest_id)) {
                $guests->push($entry->guest);
            }
        }

        return view('admin.create', compact('services', 'service', 'date', 'staffId', 'guests') + ['slots' => $service ? $availability->slots($service, $date, $staffId) : [], 'requestKey' => old('request_key', Str::uuid()->toString()), 'smsReady' => Salon::current()->featureEnabled('sms') && $smsGateway->configured()]);
    }

    public function store(Request $request, PublicController $public, BookingService $bookings): RedirectResponse
    {
        $booking = $bookings->create($public->bookingData($request, true), $request->user());

        return redirect()->route('admin.booking', $booking)->with('success', 'A foglalást elmentettük.');
    }

    public function booking(Booking $booking, Request $request, Availability $availability, BenefitsService $benefits): View
    {
        $this->authorizeBooking($booking, $request);
        $date = $request->query('date', $booking->starts_at->setTimezone(Salon::current()->timezone)->toDateString());
        $service = $booking->service;
        $service->duration_minutes = $booking->duration_minutes;
        $service->buffer_before = $booking->buffer_before;
        $service->buffer_after = $booking->buffer_after;
        $staffId = $request->user()->role === 'owner' ? null : $request->user()->staff_id;

        return view('admin.booking', compact('booking', 'date') + ['slots' => $availability->slots($service, $date, $staffId, $booking), 'usablePasses' => $benefits->usablePassesFor($booking)]);
    }

    public function updateBooking(Booking $booking, Request $request, BookingService $bookings): RedirectResponse
    {
        $this->authorizeBooking($booking, $request, true);
        $data = $request->validate(['action' => ['required', Rule::in(['move', 'cancel', 'complete', 'no_show'])], 'revision' => 'required|integer', 'slot' => 'required_if:action,move|string', 'actual_amount' => 'nullable|integer|min:0|max:10000000', 'guest_pass_id' => 'nullable|integer|exists:guest_passes,id']);
        if (! Salon::current()->featureEnabled('financials')) {
            unset($data['actual_amount']);
        }
        if ($data['action'] === 'move') {
            $parts = explode('|', $data['slot'], 2);
            abort_unless(count($parts) === 2 && ctype_digit($parts[0]), 422);
            [$data['staff_id'],$data['starts_at']] = $parts;
        }
        $bookings->change($booking, $data, $request->user());

        return back()->with('success', 'A foglalást frissítettük.');
    }

    public function guests(Request $request): View
    {
        $search = mb_substr($request->query('q', ''), 0, 120);
        $guests = $this->guestQuery($request)->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))->orderBy('name')->paginate(30);

        return view('admin.guests', compact('guests', 'search'));
    }

    public function guest(Guest $guest, Request $request): View
    {
        abort_unless($this->guestQuery($request)->whereKey($guest->id)->exists(), 403);
        $bookings = $guest->bookings()->visibleTo($request->user())->with('staff')->orderByDesc('starts_at')->get();
        $duplicates = $this->guestQuery($request)->where('id', '!=', $guest->id)->whereNull('anonymized_at')
            ->where(function ($query) use ($guest): void {
                if ($guest->email) {
                    $query->where('email', $guest->email);
                }
                if ($guest->phone) {
                    $guest->email ? $query->orWhere('phone', $guest->phone) : $query->where('phone', $guest->phone);
                }
            })->when(! $guest->email && ! $guest->phone, fn ($query) => $query->whereRaw('1 = 0'))->count();

        $benefitData = $request->user()->role === 'owner' && (Salon::current()->featureEnabled('passes') || Salon::current()->featureEnabled('loyalty'))
            ? app(BenefitsService::class)->guestData($guest) : [];

        return view('admin.guest', compact('guest', 'bookings', 'duplicates') + $benefitData + ['noShowCount' => $bookings->where('status', 'no_show')->count()]);
    }

    public function notes(Guest $guest, Request $request): RedirectResponse
    {
        abort_if($guest->anonymized_at, 422);
        abort_unless(($request->user()->role === 'owner' || $request->user()->can_view_notes) && $this->guestQuery($request)->whereKey($guest->id)->exists(), 403);
        $data = $request->validate(['notes' => 'nullable|string|max:3000']);
        $guest->update($data);

        return back()->with('success', 'A megjegyzést elmentettük.');
    }

    public function contact(Guest $guest, Request $request): RedirectResponse
    {
        abort_unless(($request->user()->role === 'owner' || $request->user()->can_manage_bookings) && $this->guestQuery($request)->whereKey($guest->id)->exists() && ! $guest->anonymized_at, 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'nullable|email:rfc|max:200', 'phone' => 'nullable|string|max:40']);
        if (empty($data['email']) && empty($data['phone'])) {
            throw ValidationException::withMessages(['phone' => 'Telefonszám vagy e-mail-cím szükséges a vendég eléréséhez.']);
        }
        $this->mutate(fn () => $guest->update($data));

        return back()->with('success', 'A kapcsolati adatokat elmentettük. Más kartonnal nem történt összevonás.');
    }

    public function export(Guest $guest): StreamedResponse
    {
        return response()->streamDownload(function () use ($guest): void {
            $bookingIds = $guest->bookings()->select('id');
            $passIds = GuestPass::where('guest_id', $guest->id)->select('id');
            echo json_encode([
                'guest' => $guest->makeVisible('notes')->toArray(),
                'visits' => $guest->bookings()->get()->toArray(),
                'waitlist' => WaitlistEntry::where('guest_id', $guest->id)->get()->toArray(),
                'passes' => GuestPass::where('guest_id', $guest->id)->get()->toArray(),
                'pass_uses' => PassUse::whereIn('guest_pass_id', $passIds)->get()->toArray(),
                'loyalty_entries' => LoyaltyEntry::where('guest_id', $guest->id)->get()->toArray(),
                'email_notifications' => OutboxMessage::whereIn('booking_id', $bookingIds)->get()->toArray(),
                'sms_notifications' => SmsOutboxMessage::whereIn('booking_id', $bookingIds)->get()->toArray(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, 'vendeg-'.$guest->id.'.json', ['Content-Type' => 'application/json']);
    }

    public function anonymize(Guest $guest, Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|current_password', 'confirm' => 'accepted']);
        $this->mutate(function () use ($guest): void {
            if ($guest->bookings()->where('status', 'confirmed')->where('starts_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['confirm' => 'Előbb rendezd a vendég jövőbeli foglalásait.']);
            }
            $guest->update(['name' => 'Törölt vendég #'.$guest->id, 'email' => 'deleted-'.$guest->id.'@example.invalid', 'phone' => null, 'notes' => null, 'anonymized_at' => now()]);
            $guest->bookings()->update(['token_expires_at' => now(), 'manage_token' => Crypt::encryptString(bin2hex(random_bytes(32)))]);
            $guest->update(['review_opt_in_at' => null, 'review_opt_out_at' => now()]);
            OutboxMessage::whereIn('booking_id', $guest->bookings()->select('id'))->whereIn('status', ['pending', 'retry', 'failed'])->update(['status' => 'obsolete']);
            SmsOutboxMessage::whereIn('booking_id', $guest->bookings()->select('id'))->whereIn('status', ['pending', 'retry', 'failed'])->update(['status' => 'obsolete']);
            WaitlistEntry::where('guest_id', $guest->id)->where('status', 'waiting')->update(['status' => 'cancelled']);
            WaitlistEntry::where('guest_id', $guest->id)->update(['note' => null]);
        });

        return back()->with('success', 'A személyes adatokat töröltük; a látogatási összesítés megmaradt.');
    }

    public function services(): View
    {
        return view('admin.services', ['services' => Service::with('staff')->get(), 'staff' => Staff::where('active', true)->get()]);
    }

    public function saveService(Request $request, ?Service $service = null): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'category' => 'required|string|max:80', 'description' => 'nullable|string|max:1000', 'duration_minutes' => 'required|integer|min:5|max:720', 'price' => 'required|integer|min:0|max:10000000', 'price_kind' => ['required', Rule::in(['fixed', 'from', 'indicative'])], 'buffer_before' => 'required|integer|min:0|max:180', 'buffer_after' => 'required|integer|min:0|max:180', 'staff' => 'nullable|array', 'staff.*' => 'integer|exists:staff,id']);
        $this->mutate(function () use ($service, $data, $request): void {
            $ids = $data['staff'] ?? [];
            unset($data['staff']);
            $data['active'] = $request->boolean('active');
            $item = $service ?? new Service;
            $item->fill($data)->save();
            $item->staff()->sync($ids);
        });

        return back()->with('success', 'A szolgáltatást elmentettük. A korábbi foglalások változatlanok.');
    }

    public function staff(): View
    {
        return view('admin.staff', ['staff' => Staff::all(), 'blocks' => TimeBlock::orderByDesc('starts_at')->limit(40)->get(), 'exceptions' => WorkException::orderByDesc('date')->limit(40)->get(), 'users' => User::where('role', 'staff')->get()->keyBy('staff_id')]);
    }

    public function saveStaff(Request $request, ?Staff $staff = null): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'title' => 'nullable|string|max:120', 'bio' => 'nullable|string|max:1500', 'email' => ['nullable', 'email', Rule::unique('users')->ignore(User::where('staff_id', $staff?->id)->where('role', 'staff')->value('id'))], 'password' => ($request->filled('email') && ! $staff ? 'required' : 'nullable').'|string|min:12|max:200', 'hours' => 'required|array']);
        $hours = $this->hours($data['hours']);
        $this->mutate(function () use ($staff, $data, $hours, $request): void {
            $item = $staff ?? new Staff;
            $item->fill(['name' => $data['name'], 'title' => $data['title'], 'bio' => $data['bio'], 'active' => $request->boolean('active'), 'weekly_hours' => $hours])->save();
            if (! empty($data['email'])) {
                $user = User::firstOrNew(['staff_id' => $item->id, 'role' => 'staff']);
                if (! $user->exists && empty($data['password'])) {
                    throw ValidationException::withMessages(['password' => 'Új belépéshez legalább 12 karakteres jelszó szükséges.']);
                }
                $user->fill(['name' => $data['name'], 'email' => $data['email'], 'can_manage_bookings' => $request->boolean('can_manage_bookings'), 'can_view_notes' => $request->boolean('can_view_notes')]);
                if (! empty($data['password'])) {
                    $user->password = $data['password'];
                }
                $user->save();
            }
        });

        return back()->with('success', 'A munkatársat és a munkarendet elmentettük. A meglévő foglalások megmaradtak.');
    }

    public function block(Request $request): RedirectResponse
    {
        $data = $request->validate(['staff_id' => 'required|exists:staff,id', 'starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at', 'reason' => 'required|string|max:120']);
        $this->mutate(function () use ($data): void {
            $zone = Salon::current()->timezone;
            $from = CarbonImmutable::parse($data['starts_at'], $zone);
            $until = CarbonImmutable::parse($data['ends_at'], $zone);
            if ($from->format('Y-m-d\TH:i') !== $data['starts_at'] || $until->format('Y-m-d\TH:i') !== $data['ends_at']) {
                throw ValidationException::withMessages(['starts_at' => 'Az óraátállítás miatt ez az időpont nem létezik.']);
            }
            TimeBlock::create(['staff_id' => $data['staff_id'], 'starts_at' => $from->utc(), 'ends_at' => $until->utc(), 'reason' => $data['reason']]);
        });

        return back()->with('success', 'A blokkolást felvettük. A korábban felvett foglalásokat ellenőrizd a naptárban.');
    }

    public function exception(Request $request): RedirectResponse
    {
        $data = $request->validate(['staff_id' => 'required|exists:staff,id', 'date' => 'required|date_format:Y-m-d', 'start' => 'nullable|date_format:H:i', 'end' => 'nullable|date_format:H:i|after:start']);
        $this->mutate(fn () => WorkException::updateOrCreate(['staff_id' => $data['staff_id'], 'date' => $data['date']], ['hours' => $request->boolean('closed') ? [] : $this->hours([1 => ['start' => $data['start'], 'end' => $data['end']]])[1]]));

        return back()->with('success', 'Az adott napi munkarendet elmentettük.');
    }

    public function removeBlock(TimeBlock $block): RedirectResponse
    {
        $this->mutate(fn () => $block->delete());

        return back()->with('success', 'A blokkolást feloldottuk.');
    }

    public function removeException(WorkException $exception): RedirectResponse
    {
        $this->mutate(fn () => $exception->delete());

        return back()->with('success', 'Ismét a heti munkarend érvényes.');
    }

    public function settings(Availability $availability): View
    {
        return view('admin.settings', ['ready' => $availability->ready()]);
    }

    public function applyPreset(Request $request, Branding $branding): RedirectResponse
    {
        $data = $request->validate(['preset' => ['required', Rule::in(array_keys(config('branding.presets')))]]);
        $preset = $branding->preset($data['preset']);
        $this->mutate(fn () => Salon::current()->update(['theme' => $data['preset'], 'accent' => $preset['accent'], 'font' => $preset['font'], 'appearance' => $preset['appearance']]));

        return back()->with('success', $preset['label'].' arculat alkalmazva. A saját szövegek, feltöltött képek és foglalási adatok megmaradtak.');
    }

    public function saveSettings(Request $request, Availability $availability): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'tagline' => 'required|string|max:150', 'about' => 'nullable|string|max:3000', 'email' => 'required|email|max:200', 'phone' => 'required|string|max:40', 'address' => 'required|string|max:200', 'theme' => ['required', Rule::in(['salon', 'barber', 'nails'])], 'accent' => 'required|regex:/^#[a-fA-F0-9]{6}$/', 'font' => ['required', Rule::in(['serif', 'sans'])], 'terms' => 'required|string|max:5000', 'privacy' => 'required|string|max:10000', 'min_notice_minutes' => 'required|integer|min:0|max:10080', 'horizon_days' => 'required|integer|min:1|max:365', 'cancel_notice_hours' => 'required|integer|min:0|max:168', 'reminder_hours' => 'required|integer|min:0|max:168', 'retention_days' => 'required|integer|min:30|max:3650', 'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', 'hero_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096', 'appearance' => 'sometimes|array:paper,ink,muted,surface,line,layout,radius,eyebrow,cta_label,show_team,show_gallery', 'appearance.paper' => 'required_with:appearance|regex:/^#[a-fA-F0-9]{6}$/', 'appearance.ink' => 'required_with:appearance|regex:/^#[a-fA-F0-9]{6}$/', 'appearance.muted' => 'required_with:appearance|regex:/^#[a-fA-F0-9]{6}$/', 'appearance.surface' => 'required_with:appearance|regex:/^#[a-fA-F0-9]{6}$/', 'appearance.line' => 'required_with:appearance|regex:/^#[a-fA-F0-9]{6}$/', 'appearance.layout' => ['required_with:appearance', Rule::in(['immersive', 'split'])], 'appearance.radius' => ['required_with:appearance', Rule::in(['4', '8', '16', '24'])], 'appearance.eyebrow' => 'required_with:appearance|string|max:100', 'appearance.cta_label' => 'required_with:appearance|string|max:40', 'appearance.show_team' => 'sometimes|boolean', 'appearance.show_gallery' => 'sometimes|boolean', 'gallery' => 'nullable|array|max:8', 'gallery.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096', 'hours' => 'required|array', 'content_rights' => 'accepted', 'current_password' => 'nullable|required_with:new_password|current_password', 'new_password' => 'nullable|string|min:12|max:200']);
        $this->mutate(function () use ($request, $data, $availability): void {
            $salon = Salon::current();
            unset($data['logo'],$data['hero_image'],$data['gallery'],$data['hours'],$data['content_rights'],$data['current_password'],$data['new_password']);
            if (isset($data['appearance'])) {
                $branding = app(Branding::class);
                foreach (['ink', 'muted'] as $color) {
                    if ($branding->contrast($data['appearance'][$color], $data['appearance']['paper']) < 4.5 || $branding->contrast($data['appearance'][$color], $data['appearance']['surface']) < 4.5) {
                        throw ValidationException::withMessages(['appearance.'.$color => 'A szövegszín és a háttér kontrasztja legalább 4,5:1 legyen az olvashatósághoz.']);
                    }
                }
                $data['appearance']['show_team'] = $request->boolean('appearance.show_team');
                $data['appearance']['show_gallery'] = $request->boolean('appearance.show_gallery');
            }
            $data['opening_hours'] = $this->hours($request->input('hours'));
            $data['published'] = false;
            $salon->update($data);
            if ($request->boolean('reset_logo')) {
                $salon->logo = null;
            }
            if ($request->boolean('reset_hero')) {
                $salon->hero_image = null;
            }
            if ($request->hasFile('hero_image')) {
                $salon->hero_image = $request->file('hero_image')->store('branding', 'public');
            }
            if ($request->hasFile('logo')) {
                $salon->logo = $request->file('logo')->store('branding', 'public');
            }
            $gallery = $request->boolean('clear_gallery') ? [] : ($salon->gallery ?? []);
            foreach ($request->file('gallery', []) as $file) {
                $gallery[] = $file->store('gallery', 'public');
            }
            $salon->gallery = array_slice($gallery, -8);
            $salon->save();
            if ($request->boolean('published')) {
                if (! $availability->ready()) {
                    throw ValidationException::withMessages(['published' => 'Legalább egy aktív szolgáltatás, alkalmas munkatárs és elegendő közös munkaidő szükséges.']);
                }
                $salon->update(['published' => true]);
            }
            if ($request->filled('new_password')) {
                $request->user()->update(['password' => $request->input('new_password')]);
            }
        });

        return back()->with('success', 'A beállításokat elmentettük.');
    }

    public function notifications(SmsGateway $smsGateway, OperationsHealth $health): View
    {
        return view('admin.notifications', [
            'messages' => OutboxMessage::orderByDesc('id')->paginate(30, ['*'], 'email_page'),
            'smsMessages' => SmsOutboxMessage::orderByDesc('id')->paginate(30, ['*'], 'sms_page'),
            'smsConfigured' => $smsGateway->configured(),
            'dueCounts' => $health->dueCounts(),
            'lastRuns' => ['email' => $health->lastRun('email'), 'sms' => $health->lastRun('sms')],
        ]);
    }

    public function retry(OutboxMessage $message): RedirectResponse
    {
        $this->mutate(function () use ($message): void {
            $message->refresh();
            abort_unless(in_array($message->status, ['failed', 'retry']), 422);
            abort_unless($message->booking->revision === $message->revision, 422);
            $message->update(['status' => 'pending', 'attempts' => 0, 'scheduled_at' => now(), 'error_code' => null]);
        });

        return back()->with('success', 'Az értesítést újra sorba állítottuk.');
    }

    public function retrySms(SmsOutboxMessage $message, SmsGateway $smsGateway): RedirectResponse
    {
        abort_unless(Salon::current()->featureEnabled('sms') && $smsGateway->configured(), 422);
        $this->mutate(function () use ($message, $smsGateway): void {
            $message->refresh();
            $booking = $message->booking()->with('guest')->firstOrFail();
            abort_unless(in_array($message->status, ['failed', 'retry'], true)
                && $message->kind === 'reminder'
                && $booking->status === 'confirmed'
                && $booking->starts_at->isFuture()
                && $booking->revision === $message->revision
                && $smsGateway->normalizeNumber($booking->guest->phone), 422);
            $message->update(['status' => 'pending', 'attempts' => 0, 'scheduled_at' => now(), 'error_code' => null]);
        });

        return back()->with('success', 'Az SMS-emlékeztetőt újra sorba állítottuk.');
    }

    private function guestQuery(Request $request): Builder
    {
        return Guest::query()->when($request->user()->role !== 'owner', function (Builder $query) use ($request): void {
            $query->where(function (Builder $visible) use ($request): void {
                $visible->whereHas('bookings', fn (Builder $bookings) => $bookings->visibleTo($request->user()));
                if (Salon::current()->featureEnabled('waitlist')) {
                    $visible->orWhereIn('id', WaitlistEntry::visibleTo($request->user())->select('guest_id'));
                }
            });
        });
    }

    private function authorizeBooking(Booking $booking, Request $request, bool $write = false): void
    {
        abort_unless($request->user()->role === 'owner' || ($request->user()->staff_id === $booking->staff_id && (! $write || $request->user()->can_manage_bookings)), 403);
    }

    private function mutate(\Closure $action): mixed
    {
        return DB::transaction(function () use ($action) {
            Salon::whereKey(1)->increment('lock_version');

            return $action();
        }, 5);
    }

    private function hours(array $input): array
    {
        $hours = [];
        foreach (range(1, 7) as $day) {
            $row = $input[$day] ?? [];
            $hours[$day] = [];
            if (! empty($row['closed']) || empty($row['start']) && empty($row['end'])) {
                continue;
            }
            foreach (['start', 'end', 'break_start', 'break_end'] as $key) {
                if (! empty($row[$key]) && ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $row[$key])) {
                    throw ValidationException::withMessages(['hours' => 'Érvénytelen munkaidő.']);
                }
            }
            if (empty($row['start']) || empty($row['end']) || $row['start'] >= $row['end']) {
                throw ValidationException::withMessages(['hours' => 'A zárás legyen a kezdés után.']);
            }
            if (! empty($row['break_start']) || ! empty($row['break_end'])) {
                if (empty($row['break_start']) || empty($row['break_end']) || $row['break_start'] <= $row['start'] || $row['break_end'] >= $row['end'] || $row['break_start'] >= $row['break_end']) {
                    throw ValidationException::withMessages(['hours' => 'A szünet a munkaidőn belül legyen.']);
                }
                $hours[$day] = [[$row['start'], $row['break_start']], [$row['break_end'], $row['end']]];
            } else {
                $hours[$day] = [[$row['start'], $row['end']]];
            }
        }

        return $hours;
    }
}
