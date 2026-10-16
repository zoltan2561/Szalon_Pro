@extends('layouts.public')
@section('title','Időpontfoglalás')
@section('content')
<div class="booking-wrap wrap"><p class="eyebrow">EGY LÉPÉSSEL KÖZELEBB</p><h1 class="page-title">A következő énidőd.</h1><ol class="steps"><li>01 <span>Szolgáltatás</span></li><li>02 <span>Időpont</span></li><li>03 <span>Adataid és véglegesítés</span></li></ol>
@if(!$ready || !$salon->published)<div class="notice">Az online foglalás még nem aktív. Kérjük, keresd a szalont az elérhetőségein.</div>
@else

<div class="booking-grid"><div><form method="get" action="{{ route('booking.form') }}" class="panel filter-form"><h2>Mit választasz?</h2><label>Szolgáltatás<select name="service_id" required>@foreach($services as $item)<option value="{{ $item->id }}" @selected($service?->id===$item->id)>{{ $item->name }} · {{ $item->duration_minutes }} perc</option>
@endforeach
</select></label><div class="form-grid"><label>Szakember<select name="staff_id"><option value="">Mindegy, kihez</option>@foreach($service?->staff->where('active',true) ?? [] as $person)<option value="{{ $person->id }}" @selected($staffId===$person->id)>{{ $person->name }}</option>
@endforeach
</select></label><label>Nap<input type="date" name="date" value="{{ $date }}" min="{{ now($salon->timezone)->toDateString() }}" max="{{ now($salon->timezone)->addDays($salon->horizon_days)->toDateString() }}" required></label></div><button class="button secondary">Szabad időpontok mutatása</button></form>
<form method="post" action="{{ route('booking.store') }}" class="panel booking-submit">@csrf<input type="hidden" name="service_id" value="{{ $service?->id }}"><input type="hidden" name="request_key" value="{{ $requestKey }}"><h2>Mikor érkezel?</h2><p class="muted">{{ $date }} · {{ $salon->timezone }}</p>@include('components.slots')<hr><h2>Hogyan érhetünk el?</h2><label>Neved<input name="name" autocomplete="name" maxlength="120" value="{{ old('name') }}" required></label><div class="form-grid"><label>E-mail-cím<input type="email" name="email" autocomplete="email" value="{{ old('email') }}" required></label><label>Telefonszám <span class="muted">(opcionális)</span><input type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" maxlength="40"></label></div><label class="check"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))><span>Megismertem a foglalási feltételeket és az <a href="{{ route('privacy') }}" target="_blank" rel="noopener">adatkezelési tájékoztatót</a>.</span></label><p class="muted">A kiválasztott időpontnál látod a szakember nevét. A foglalás a véglegesítéskor válik érvényessé.</p><button class="button full" @disabled(!$slots)>Foglalás véglegesítése <span aria-hidden="true">↗</span></button></form></div><aside class="booking-summary panel"><span class="eyebrow">A FOGLALÁSOD</span><h2>{{ $service?->name }}</h2><p>{{ $service?->description }}</p><div class="summary-price">{{ number_format($service?->price ?? 0,0,',',' ') }} Ft{{ $service?->price_kind==='from' ? '-tól':'' }}</div><p>{{ $service?->duration_minutes }} perc @if($service?->price_kind==='indicative')· Tájékoztató ár
@endif
</p><hr><strong>{{ $salon->name }}</strong><p>{{ $salon->address }}</p><hr><h3>Ha közbejön valami</h3><p class="preserve">{{ $salon->terms }}</p><p class="muted">A visszaigazolást és a foglalásod kezelési linkjét e-mailben küldjük.</p></aside></div>
@endif
</div>

@endsection
