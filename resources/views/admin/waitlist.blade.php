@extends('layouts.admin')
@section('title', 'Várólista')
@section('content')
<div class="page-heading"><div><p class="eyebrow">HA FELSZABADUL EGY IDŐPONT</p><h1>Várólista</h1><p class="muted">{{ $automatic ? 'A hozzájárult vendégek automatikus ajánlatot kapnak. A helyet a vendég megerősítése foglalja le.' : 'Most kézzel egyeztetsz a vendégekkel. Az automatikus ajánlat a Modulok oldalon, beállított küldéssel kapcsolható be.' }}</p></div></div>
<div class="booking-grid"><div>
    <section class="panel"><h2>Várakozó vendégek</h2>
        @forelse($entries as $entry)
            <div class="history-row"><div><strong>{{ $entry->guest->name }}</strong><p>{{ $entry->service->name }}{{ $entry->staff ? ' · '.$entry->staff->name : ' · bármelyik szakember' }}{{ $entry->preferred_date ? ' · '.$entry->preferred_date->format('Y. m. d.') : '' }}</p><p>{{ $entry->guest->phone ?: $entry->guest->email }}{{ $entry->note ? ' · '.$entry->note : '' }}</p></div>
                @if(auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)
                    <div><div class="actions"><a class="button secondary" href="{{ route('admin.create', array_filter(['guest_id' => $entry->guest_id, 'service_id' => $entry->service_id, 'staff_id' => $entry->staff_id, 'date' => $entry->preferred_date?->toDateString(), 'waitlist_entry_id' => $entry->id])) }}">Időpont keresése</a><form method="post" action="{{ route('admin.waitlist.cancel', $entry) }}">@csrf<button class="button secondary">Lezárás</button></form></div>
                    @if(!$entry->notification_consent_at)<details><summary>Értesítést kér a vendég?</summary><form method="post" action="{{ route('admin.waitlist.consent', $entry) }}">@csrf<label class="check"><input type="checkbox" name="notify_consent" value="1" required><span>A vendég kér értesítést a megfelelő szabad helyekről.</span></label><button class="button secondary">Kérés rögzítése</button></form></details>@else<p class="muted">A vendég kér értesítést a szabad helyekről.</p>@endif</div>
                @endif
            </div>
        @empty
            <p class="muted">Most nincs várakozó vendég.</p>
        @endforelse
        {{ $entries->links() }}
    </section>
    @if($recent->isNotEmpty())<section class="panel"><h2>Nemrég lezárt kérések</h2>@foreach($recent as $entry)<div class="history-row"><div><strong>{{ $entry->guest->name }}</strong><p>{{ $entry->service->name }} · {{ $entry->status === 'booked' ? 'Foglalva' : 'Lezárva' }}</p></div>@if($entry->booking)<a href="{{ route('admin.booking', $entry->booking) }}">Foglalás ↗</a>@endif</div>@endforeach</section>@endif
</div>
@if(auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)
<aside class="panel"><h2>Új várólistás kérés</h2><form method="post" action="{{ route('admin.waitlist.store') }}">@csrf
    <label>Szolgáltatás<select name="service_id" required><option value="">Válassz szolgáltatást</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }}</option>@endforeach</select></label>
    @if(auth()->user()->role === 'owner')<label>Szakember<select name="staff_id"><option value="">Bármelyik alkalmas szakember</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected(old('staff_id') == $person->id)>{{ $person->name }}</option>@endforeach</select></label>@else<p class="muted">Szakember: {{ $staff->first()?->name }}</p>@endif
    <label>Kívánt nap <span class="muted">(opcionális)</span><input type="date" name="preferred_date" value="{{ old('preferred_date') }}"></label>
    <label>Utolsó megfelelő nap <span class="muted">(opcionális)</span><input type="date" name="preferred_until" value="{{ old('preferred_until') }}"></label>
    <div class="form-grid"><label>Ettől az órától<input type="time" name="time_from" value="{{ old('time_from') }}"></label><label>Eddig az óráig<input type="time" name="time_until" value="{{ old('time_until') }}"></label></div>
    <hr><label>Meglévő vendég<select name="guest_id"><option value="">Új vendéget adok meg</option>@foreach($guests as $guest)<option value="{{ $guest->id }}" @selected(old('guest_id') == $guest->id)>{{ $guest->name }} · {{ $guest->phone ?: $guest->email }}</option>@endforeach</select></label>
    <p class="muted">Meglévő vendégnél az alábbi adatokat hagyd üresen.</p>
    <label>Új vendég neve<input name="name" value="{{ old('name') }}" maxlength="120"></label>
    <div class="form-grid"><label>Telefon<input name="phone" type="tel" value="{{ old('phone') }}" maxlength="40"></label><label>E-mail-cím<input name="email" type="email" value="{{ old('email') }}"></label></div>
    <p class="muted">Legalább egy elérhetőség kell az egyeztetéshez.</p>
    <label>Rövid egyeztetési megjegyzés<textarea name="note" rows="2" maxlength="255">{{ old('note') }}</textarea></label><p class="muted">Érzékeny vagy egészségügyi adatot ide ne írj.</p>
    <label class="check"><input type="checkbox" name="notify_consent" value="1" @checked(old('notify_consent'))><span>A vendég kér értesítést a megfelelő szabad helyekről.</span></label>
    <button class="button full">Felvétel a várólistára</button>
</form></aside>
@endif
</div>
@if($offers->isNotEmpty())<section class="panel"><h2>Szabadhely-ajánlatok</h2>@foreach($offers as $offer)<div class="history-row"><div><strong>{{ $offer->entry->guest->name }}</strong><p>{{ $offer->entry->service->name }} · {{ $offer->starts_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }} · {{ $offer->staff->name }}</p></div><span class="badge">{{ ['pending'=>'Küldésre vár','retry'=>'Újrapróbáljuk','sending'=>'Küldés folyamatban','sent'=>'Értesítés elküldve','accepted'=>'Lefoglalva','expired'=>'Lejárt','obsolete'=>'Már nem aktuális','failed'=>'Nem sikerült küldeni','uncertain'=>'A küldés eredménye nem ismert'][$offer->status] ?? 'Ellenőrzendő' }}</span></div>@endforeach</section>@endif
@endsection
