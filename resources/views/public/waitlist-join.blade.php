@extends('layouts.public')
@section('title', 'Szóljatok, ha lesz szabad időpont')
@section('content')
<div class="wrap narrow"><p class="eyebrow">HA MOST NINCS JÓ IDŐPONT</p><h1>Szóljatok, ha lesz hely!</h1><p>Add meg, mikor érnél rá. Ez még nem foglalás: a felajánlott időpontot külön fogadhatod el.</p>
<form class="panel" method="post" action="{{ route('waitlist.store') }}" data-waitlist-preferences>@csrf
    <div hidden aria-hidden="true"><label>Weboldal<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <label>Szolgáltatás<select name="service_id" required>@foreach($services as $item)<option value="{{ $item->id }}" @selected(old('service_id', $service?->id) == $item->id)>{{ $item->name }}</option>@endforeach</select></label>
    <label>Szakember<select name="staff_id"><option value="">Bármelyik alkalmas szakember</option>@foreach($services->flatMap->staff->where('active', true)->unique('id') as $person)<option value="{{ $person->id }}" data-services="{{ $services->filter(fn ($item) => $item->staff->contains('id', $person->id))->pluck('id')->join(',') }}" @selected(old('staff_id', $staffId) == $person->id)>{{ $person->name }}</option>@endforeach</select></label>
    <div class="form-grid"><label>Első megfelelő nap<input type="date" name="preferred_date" min="{{ now($salon->timezone)->toDateString() }}" max="{{ now($salon->timezone)->addDays($salon->horizon_days)->toDateString() }}" value="{{ old('preferred_date', $date) }}" required></label><label>Eddig várok időpontra<input type="date" name="preferred_until" min="{{ now($salon->timezone)->toDateString() }}" max="{{ now($salon->timezone)->addDays($salon->horizon_days)->toDateString() }}" value="{{ old('preferred_until', \Carbon\CarbonImmutable::parse($date, $salon->timezone)->addDays(13)->min(now($salon->timezone)->addDays($salon->horizon_days))->toDateString()) }}" required></label></div>
    <details><summary>Csak bizonyos napszakban érek rá</summary><div class="form-grid"><label>Ettől az órától<input type="time" name="time_from" value="{{ old('time_from') }}"></label><label>Eddig az óráig<input type="time" name="time_until" value="{{ old('time_until') }}"></label></div></details>
    <label>Neved<input name="name" autocomplete="name" maxlength="120" value="{{ old('name') }}" required></label>
    <div class="form-grid"><label>E-mail-cím<input type="email" name="email" autocomplete="email" value="{{ old('email') }}" required></label><label>Telefon <span class="muted">(opcionális)</span><input type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" maxlength="40"></label></div>
    <label class="check"><input type="checkbox" name="notify_consent" value="1" required @checked(old('notify_consent'))><span>Kérem, hogy a szalon keressen a kérésemnek megfelelő szabad időponttal. Megismertem az <a href="{{ route('privacy') }}">adatkezelési tájékoztatót</a>.</span></label>
    <p class="muted">{{ $automatic ? 'Ha megfelelő hely szabadul fel, értesítést kapsz. Az ajánlat nem tartja fenn az időpontot.' : 'A szalon a megadott elérhetőségeden egyeztet veled.' }}</p><button class="button full">Feliratkozom a várólistára</button>
</form></div>
@endsection
