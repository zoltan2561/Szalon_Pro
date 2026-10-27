<?php

namespace App\Http\Controllers;

use App\Models\GoogleCalendarConnection;
use App\Models\Salon;
use App\Models\Staff;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleCalendarRelay;
use App\Services\GoogleCalendarSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class GoogleCalendarController extends Controller
{
    public function index(Request $request, GoogleCalendarClient $client): View
    {
        return view('admin.google-calendar', [
            'staff' => Staff::when($request->user()->role !== 'owner', fn ($query) => $query->whereKey($request->user()->staff_id))->orderBy('name')->get(),
            'connections' => GoogleCalendarConnection::withCount('busyEvents')->when($request->user()->role !== 'owner', fn ($query) => $query->where('staff_id', $request->user()->staff_id))->get()->keyBy('staff_id'),
            'configured' => $client->configured(),
        ]);
    }

    public function connect(Staff $staff, Request $request, GoogleCalendarClient $client, GoogleCalendarRelay $relay): RedirectResponse
    {
        $this->authorizeStaff($request, $staff->id);
        abort_unless($staff->active, 422);
        if (! $client->configured()) {
            return back()->withErrors(['google' => 'A Google Naptár kapcsolódás még nincs beállítva.']);
        }
        try {
            $state = $relay->state(Str::random(64));
        } catch (\RuntimeException) {
            return back()->withErrors(['google' => 'A közös Google-kapcsolat beállítása hiányos. Kérd az üzemeltető segítségét.']);
        }
        $request->session()->put('google_calendar_oauth', ['state' => $state, 'staff_id' => $staff->id, 'user_id' => $request->user()->id, 'started_at' => now()->timestamp]);

        return redirect()->away($client->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleCalendarClient $client, GoogleCalendarSync $sync): RedirectResponse
    {
        $pending = $request->session()->get('google_calendar_oauth');
        if (! is_array($pending) || ! is_string($pending['state'] ?? null) || ! is_string($request->query('state'))
            || ! hash_equals($pending['state'], $request->query('state')) || ($pending['user_id'] ?? null) !== $request->user()->id
            || ($pending['started_at'] ?? 0) < now()->subMinutes(10)->timestamp) {
            return redirect()->route('admin.google.index')->withErrors(['google' => 'A kapcsolódási kérés lejárt vagy érvénytelen. Indítsd újra.']);
        }
        $request->session()->forget('google_calendar_oauth');
        if ($request->filled('error')) {
            return redirect()->route('admin.google.index')->withErrors(['google' => 'A Google hozzáférés nem lett engedélyezve.']);
        }
        $code = $request->query('code');
        if (! is_string($code) || strlen($code) > 4096 || ! $code) {
            return redirect()->route('admin.google.index')->withErrors(['google' => 'Hiányzik az engedélyezési kód.']);
        }
        $staff = Staff::whereKey($pending['staff_id'])->where('active', true)->first();
        if (! $staff) {
            return redirect()->route('admin.google.index')->withErrors(['google' => 'A szakember kapcsolata időközben megváltozott.']);
        }
        $this->authorizeStaff($request, $staff->id);
        try {
            $token = $client->exchangeCode($code);
            $calendar = $client->primaryCalendar($token['access_token']);
            if ($calendar['timeZone'] !== Salon::current()->timezone) {
                return redirect()->route('admin.google.index')->withErrors(['google' => 'A Google Naptár időzónája eltér a szalonétól. Állítsd azonosra a Google Naptárban, majd próbáld újra.']);
            }
            $calendarId = $calendar['id'];
            $connection = GoogleCalendarConnection::where('staff_id', $staff->id)->first();
            if ($connection && $connection->calendar_id !== $calendarId) {
                return redirect()->route('admin.google.index')->withErrors(['google' => 'Másik Google-fiókot választottál. Ehhez a szakemberhez az eredeti naptárt engedélyezd újra, vagy előbb bontsd a korábbi kapcsolatot.']);
            }
            if (GoogleCalendarConnection::where('calendar_id', $calendarId)->where('staff_id', '!=', $staff->id)->exists()) {
                return redirect()->route('admin.google.index')->withErrors(['google' => 'Ez a Google Naptár már másik szakemberhez kapcsolódik. Válassz külön naptárat.']);
            }
            $values = [
                'calendar_id' => $calendarId,
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
                'token_expires_at' => now()->addSeconds($token['expires_in']),
                'sync_status' => 'pending',
                'last_imported_at' => null,
            ];
            if ($connection) {
                $connection->update($values);
            } else {
                $connection = GoogleCalendarConnection::create(['staff_id' => $staff->id] + $values);
            }
        } catch (Throwable) {
            return redirect()->route('admin.google.index')->withErrors(['google' => 'A Google kapcsolat létrehozása nem sikerült. Próbáld újra.']);
        }
        try {
            $sync->sync($connection);
        } catch (Throwable) {
            return redirect()->route('admin.google.index')->withErrors(['google' => 'A kapcsolat létrejött, de a szinkron hibázott. A foglalás ennél a szakembernél szünetel az újrapróbálásig.']);
        }

        return redirect()->route('admin.google.index')->with('success', $staff->name.' Google Naptára kapcsolódott.');
    }

    public function syncNow(GoogleCalendarConnection $connection, Request $request, GoogleCalendarSync $sync): RedirectResponse
    {
        $this->authorizeStaff($request, $connection->staff_id);
        try {
            $sync->sync($connection);
        } catch (Throwable) {
            return back()->withErrors(['google' => 'A szinkron nem sikerült. A részletes állapot lent látható.']);
        }

        return back()->with('success', 'A Google Naptár frissült.');
    }

    public function disconnect(GoogleCalendarConnection $connection, Request $request, GoogleCalendarClient $client): RedirectResponse
    {
        $this->authorizeStaff($request, $connection->staff_id);
        $request->validate(['confirm' => 'accepted', 'leave_events' => 'sometimes|accepted']);
        $lock = Cache::lock('google-calendar-sync-'.$connection->id, 600);
        if (! $lock->get()) {
            return back()->withErrors(['google' => 'A naptár frissítése még fut. A bontást próbáld újra később.']);
        }
        try {
            if (! $request->boolean('leave_events')) {
                foreach ($connection->events as $mapping) {
                    $client->deleteBooking($connection, $mapping->event_id);
                }
            }
            $connection->delete();
        } catch (Throwable) {
            return back()->withErrors(['google' => 'A kivitt Google-események törlése nem sikerült. Frissítsd vagy engedélyezd újra a kapcsolatot, majd próbáld újra. Szükség esetén választhatod a helyi kapcsolat bontását úgy, hogy a Google-eseményeket ott külön kezeled.']);
        } finally {
            $lock->release();
        }

        return back()->with('success', $request->boolean('leave_events') ? 'A helyi kapcsolat megszűnt. A korábbi Google-eseményeket ott külön töröld.' : 'A kapcsolat és a kivitt Google-események törlődtek.');
    }

    private function authorizeStaff(Request $request, int $staffId): void
    {
        abort_unless($request->user()->role === 'owner' || (int) $request->user()->staff_id === $staffId, 403);
    }
}
