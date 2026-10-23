<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WaitlistEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaitlistController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureEnabled();
        $user = $request->user();
        $entries = WaitlistEntry::visibleTo($user)->with('guest', 'service', 'staff')
            ->where('status', 'waiting')->orderBy('preferred_date')->orderBy('created_at')->paginate(30);
        $recent = WaitlistEntry::visibleTo($user)->with('guest', 'service', 'booking')
            ->whereIn('status', ['booked', 'cancelled'])->orderByDesc('updated_at')->limit(12)->get();
        $services = Service::where('active', true)->when($user->role !== 'owner', fn ($query) => $query->whereHas('staff', fn ($staff) => $staff->where('staff.id', $user->staff_id)))->with('staff')->get();
        $staff = $user->role === 'owner' ? Staff::where('active', true)->orderBy('name')->get() : Staff::whereKey($user->staff_id)->get();
        $guests = Guest::whereNull('anonymized_at')->when($user->role !== 'owner', fn ($query) => $query->where(fn ($visible) => $visible->whereHas('bookings', fn ($bookings) => $bookings->visibleTo($user))->orWhereIn('id', WaitlistEntry::visibleTo($user)->select('guest_id'))))
            ->orderBy('name')->get();

        return view('admin.waitlist', compact('entries', 'recent', 'services', 'staff', 'guests'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $user = $request->user();
        abort_unless($user->role === 'owner' || $user->can_manage_bookings, 403);
        abort_if($user->role !== 'owner' && ! $user->staff_id, 403);
        $data = $request->validate([
            'guest_id' => 'nullable|integer|exists:guests,id',
            'name' => 'required_without:guest_id|nullable|string|max:120',
            'email' => 'nullable|email:rfc|max:200',
            'phone' => 'nullable|string|max:40',
            'service_id' => 'required|integer|exists:services,id',
            'staff_id' => 'nullable|integer|exists:staff,id',
            'preferred_date' => 'nullable|date_format:Y-m-d',
            'note' => 'nullable|string|max:255',
        ]);
        $service = Service::where('active', true)->findOrFail($data['service_id']);
        $staffId = $user->role === 'owner' ? ($data['staff_id'] ?? null) : $user->staff_id;
        if ($staffId && ! $service->staff()->whereKey($staffId)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'A szakember nem végezheti ezt a szolgáltatást.']);
        }
        DB::transaction(function () use ($user, $data, $service, $staffId): void {
            Salon::whereKey(1)->increment('lock_version');
            if (! empty($data['guest_id'])) {
                $guest = Guest::findOrFail($data['guest_id']);
                abort_if($guest->anonymized_at, 422);
                abort_unless($user->role === 'owner' || $guest->bookings()->visibleTo($user)->exists() || WaitlistEntry::visibleTo($user)->where('guest_id', $guest->id)->exists(), 403);
            } else {
                if (empty($data['email']) && empty($data['phone'])) {
                    throw ValidationException::withMessages(['phone' => 'A várólistához telefonszám vagy e-mail-cím kell, hogy elérhesd a vendéget.']);
                }
                $guest = Guest::create(['name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null]);
            }
            if (! $guest->email && ! $guest->phone) {
                throw ValidationException::withMessages(['guest_id' => 'Ehhez a vendéghez nincs elérhetőség. Egészítsd ki a vendégkartont.']);
            }
            WaitlistEntry::create([
                'guest_id' => $guest->id, 'service_id' => $service->id, 'staff_id' => $staffId,
                'preferred_date' => $data['preferred_date'] ?? null, 'note' => $data['note'] ?? null,
                'status' => 'waiting', 'created_by_id' => $user->id,
            ]);
        }, 5);

        return redirect()->route('admin.waitlist.index')->with('success', 'A vendéget felvettük a várólistára. Szabad időpontnál a foglalást külön kell véglegesíteni.');
    }

    public function cancel(WaitlistEntry $entry, Request $request): RedirectResponse
    {
        $this->ensureEnabled();
        $user = $request->user();
        abort_unless(($user->role === 'owner' || $user->can_manage_bookings) && WaitlistEntry::visibleTo($user)->whereKey($entry->id)->exists(), 403);
        DB::transaction(function () use ($entry): void {
            Salon::whereKey(1)->increment('lock_version');
            $entry = WaitlistEntry::lockForUpdate()->findOrFail($entry->id);
            abort_unless($entry->status === 'waiting', 422);
            $entry->update(['status' => 'cancelled']);
        }, 5);

        return back()->with('success', 'A várólistás kérést lezártuk.');
    }

    private function ensureEnabled(): void
    {
        abort_unless(Salon::current()->featureEnabled('waitlist'), 404);
    }
}
