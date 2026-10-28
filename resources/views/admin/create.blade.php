@extends('layouts.admin')
@section('title', 'Új foglalás')
@section('content')
<div class="page-heading"><div><p class="eyebrow">TELEFONON, SZEMÉLYESEN, ÜZENETBEN</p><h1>Új foglalás</h1><p class="muted">A telefonos vagy személyes foglalás is a közös naptárba kerül.</p></div></div>
<div class="booking-grid"><div>
    <form method="get" class="panel" data-manual-filters><div class="form-grid">
        <label>Szolgáltatás<select name="service_id" aria-describedby="manual-service-label">@foreach($services as $item)<option value="{{ $item->id }}" @selected($service?->id === $item->id)>{{ $item->name }} · {{ $item->duration_minutes }} perc</option>@endforeach</select><span id="manual-service-label" class="manual-service-label" data-manual-service-label>{{ $service ? $service->name.' · '.$service->duration_minutes.' perc' : 'Nincs választható szolgáltatás' }}</span></label>
        <label>Nap<input type="date" name="date" value="{{ $date }}" required></label>
    </div>
    @if(auth()->user()->role === 'owner')<label>Szakember<select name="staff_id"><option value="">Bármelyik alkalmas szakember</option>@foreach($service?->staff->where('active', true) ?? [] as $person)<option value="{{ $person->id }}" @selected($staffId === $person->id)>{{ $person->name }}</option>@endforeach</select></label>@endif
    @if(request()->filled('guest_id'))<input type="hidden" name="guest_id" value="{{ request('guest_id') }}">@endif
    @if(request()->filled('waitlist_entry_id'))<input type="hidden" name="waitlist_entry_id" value="{{ request('waitlist_entry_id') }}">@endif
    <div class="actions"><button class="button secondary">Szabad időpontok mutatása</button><button class="button" name="nearest" value="1">Legközelebbi szabad időpont</button></div><p class="muted" data-nearest-status aria-live="polite"></p></form>

    <form class="panel booking-submit" method="post" action="{{ route('admin.store') }}">@csrf
        <input type="hidden" name="service_id" value="{{ $service?->id }}"><input type="hidden" name="request_key" value="{{ $requestKey }}">
        @if(request()->filled('waitlist_entry_id'))<input type="hidden" name="waitlist_entry_id" value="{{ request('waitlist_entry_id') }}">@endif
        <h2>Válassz időpontot</h2>@if($calendarStart && ! $selectedSlot)<p class="notice" data-calendar-slot-unavailable>A naptárban kiválasztott időpont ehhez a szolgáltatáshoz már nem foglalható. Válassz egy másik szabad időpontot.</p>@endif @include('components.slots', ['groupAnyStaff' => ! $staffId, 'selectedSlot' => $selectedSlot])<hr>
        <h2 id="manual-booking-guest">Ki érkezik?</h2>
        <p class="manual-selection" data-manual-selection aria-live="polite" hidden></p>
        <label>Vendég keresése név, e-mail vagy telefonszám alapján<input type="search" data-guest-search autocomplete="off" aria-controls="manual-guest-results"></label>
        <div id="manual-guest-results" class="manual-guest-results" data-guest-results hidden></div><p class="muted" data-guest-search-status aria-live="polite"></p>
        <div data-guest-fallback><label>Meglévő vendég<select id="manual-guest" name="guest_id"><option value="">Új vendég adatait adom meg</option>@foreach($guests as $guest)<option value="{{ $guest->id }}" @selected(old('guest_id', request('guest_id')) == $guest->id)>{{ $guest->name }} · {{ $guest->email ?: 'nincs e-mail' }}{{ $guest->phone ? ' · '.$guest->phone : '' }}</option>@endforeach</select></label><p class="muted">Meglévő vendég választásakor az alábbi újvendég-mezőket hagyd üresen.</p></div>
        <div class="manual-selected-guest" data-selected-guest hidden><strong data-selected-guest-name></strong><button type="button" class="text-button" data-clear-guest>Másik vendég / új vendég</button></div>
        <div data-new-guest-fields><label>Új vendég neve<input name="name" value="{{ old('name') }}" maxlength="120"></label>
        <fieldset><legend>Elérhetőség (nem kötelező)</legend><div class="form-grid"><label>E-mail-cím<input name="email" type="email" value="{{ old('email') }}" maxlength="200"></label><label>Telefonszám<input name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" maxlength="40"></label></div></fieldset>
        <p class="muted">Telefonos vagy betérő vendéghez elég a név.</p></div>
        @if($smsReady)<label class="check"><input type="checkbox" name="sms_opt_in" value="1" @checked(old('sms_opt_in'))><span>A vendég SMS-emlékeztetőt kér a megadott telefonszámra.</span></label>@endif
        <p class="muted">Mentéskor az időpont visszaigazolt állapotba kerül. Ha van e-mail-cím, a visszaigazolás küldési sorba kerül; a kézbesítés az értesítések állapotától függ.@if($smsReady && $salon->featureEnabled('phone_booking')) E-mail nélküli vendégnek a beállított SMS-szolgáltató is küldhet visszaigazolást.@endif</p>
        <button class="button full" @disabled(!$slots)>Foglalás mentése</button>
    </form>
</div><aside class="panel"><p class="eyebrow">KIVÁLASZTOTT SZOLGÁLTATÁS</p><h2>{{ $service?->name ?? 'Először vegyél fel szolgáltatást' }}</h2><div class="summary-price">{{ number_format($service?->price ?? 0, 0, ',', ' ') }} Ft{{ $service?->price_kind === 'from' ? '-tól' : '' }}</div><p>{{ $service?->duration_minutes }} perc{{ $service?->price_kind === 'indicative' ? ' · tájékoztató ár' : '' }}</p><p class="muted">Az előkészületet, az utómunkát, a munkarendet és a blokkolásokat automatikusan figyelembe vesszük.</p><p class="muted">Új időpontnál az aktuális árlista érvényes; a korábbi látogatások adatai megmaradnak.</p></aside></div>
@endsection
