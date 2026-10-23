@extends('layouts.admin')
@section('title', 'Új foglalás')
@section('content')
<div class="page-heading"><div><p class="eyebrow">TELEFONON, SZEMÉLYESEN, ÜZENETBEN</p><h1>Új foglalás</h1><p class="muted">A kézi foglalás is ugyanabba a közös naptárba kerül.</p></div></div>
<div class="booking-grid"><div>
    <form method="get" class="panel"><div class="form-grid">
        <label>Szolgáltatás<select name="service_id">@foreach($services as $item)<option value="{{ $item->id }}" @selected($service?->id === $item->id)>{{ $item->name }} · {{ $item->duration_minutes }} perc</option>@endforeach</select></label>
        <label>Nap<input type="date" name="date" value="{{ $date }}" required></label>
    </div>
    @if(auth()->user()->role === 'owner')<label>Szakember<select name="staff_id"><option value="">Bármelyik alkalmas szakember</option>@foreach($service?->staff->where('active', true) ?? [] as $person)<option value="{{ $person->id }}" @selected($staffId === $person->id)>{{ $person->name }}</option>@endforeach</select></label>@endif
    @if(request()->filled('guest_id'))<input type="hidden" name="guest_id" value="{{ request('guest_id') }}">@endif
    @if(request()->filled('waitlist_entry_id'))<input type="hidden" name="waitlist_entry_id" value="{{ request('waitlist_entry_id') }}">@endif
    <button class="button secondary">Szabad időpontok mutatása</button></form>

    <form class="panel booking-submit" method="post" action="{{ route('admin.store') }}">@csrf
        <input type="hidden" name="service_id" value="{{ $service?->id }}"><input type="hidden" name="request_key" value="{{ $requestKey }}">
        @if(request()->filled('waitlist_entry_id'))<input type="hidden" name="waitlist_entry_id" value="{{ request('waitlist_entry_id') }}">@endif
        <h2>Válassz időpontot</h2>@include('components.slots')<hr>
        <h2>Ki érkezik?</h2>
        <label>Meglévő vendég<select name="guest_id"><option value="">Új vendég adatait adom meg</option>@foreach($guests as $guest)<option value="{{ $guest->id }}" @selected(old('guest_id', request('guest_id')) == $guest->id)>{{ $guest->name }} · {{ $guest->email ?: 'nincs e-mail' }}{{ $guest->phone ? ' · '.$guest->phone : '' }}</option>@endforeach</select></label>
        <p class="muted">Meglévő vendég választásakor az alábbi újvendég-mezőket hagyd üresen.</p>
        <label>Új vendég neve<input name="name" value="{{ old('name') }}" maxlength="120"></label>
        <div class="form-grid"><label>E-mail-cím <span class="muted">(opcionális)</span><input name="email" type="email" value="{{ old('email') }}" maxlength="200"></label><label>Telefonszám <span class="muted">(opcionális)</span><input name="phone" type="tel" value="{{ old('phone') }}" maxlength="40"></label></div>
        <p class="muted">Telefonos vagy betérő vendéghez elég a név. E-mail visszaigazolás csak megadott e-mail-címre indul.</p>
        @if($smsReady)<label class="check"><input type="checkbox" name="sms_opt_in" value="1" @checked(old('sms_opt_in'))><span>A vendég SMS-emlékeztetőt kér a megadott telefonszámra.</span></label>@endif
        <button class="button full" @disabled(!$slots)>Foglalás mentése</button>
    </form>
</div><aside class="panel"><p class="eyebrow">KIVÁLASZTOTT SZOLGÁLTATÁS</p><h2>{{ $service?->name ?? 'Először vegyél fel szolgáltatást' }}</h2><div class="summary-price">{{ number_format($service?->price ?? 0, 0, ',', ' ') }} Ft{{ $service?->price_kind === 'from' ? '-tól' : '' }}</div><p>{{ $service?->duration_minutes }} perc{{ $service?->price_kind === 'indicative' ? ' · tájékoztató ár' : '' }}</p><p class="muted">Az elő- és utópuffer, a munkarend és a blokkolások automatikusan számítanak.</p><p class="muted">Új időpontnál az aktuális árlista érvényes; a korábbi látogatások adatai megmaradnak.</p></aside></div>
@endsection
