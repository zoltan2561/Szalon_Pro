@extends('layouts.public')
@section('title', 'Időpontfoglalás')
@section('content')
<div class="booking-wrap wrap">
    <p class="eyebrow">EGY LÉPÉSSEL KÖZELEBB</p>
    <h1 class="page-title">{{ $salon->demo ? $brand['demo_copy']['booking_heading'] : 'A következő énidőd.' }}</h1>
    <ol class="steps"><li>01 <span>Szolgáltatás</span></li><li>02 <span>Időpont</span></li><li>03 <span>Adataid és véglegesítés</span></li></ol>
    @if($salon->demo)<div class="notice demo-booking-notice">Ez egy bemutató. A foglalás kipróbálható, de kérjük, ne adj meg valódi személyes adatot.</div>@endif
    @if(!$ready || !$salon->published)
        <div class="notice">Az online foglalás még nem aktív. Kérjük, keresd a szalont az elérhetőségein.</div>
    @else
        <div class="booking-grid">
            <form method="get" action="{{ route('booking.form') }}" class="panel filter-form">
                <p class="eyebrow">01 · VÁLASSZ</p>
                <h2>Mit választasz?</h2>
                <label>Szolgáltatás
                    <select name="service_id" required>
                        @foreach($services as $item)<option value="{{ $item->id }}" @selected($service?->id === $item->id)>{{ $item->name }} · {{ $item->duration_minutes }} perc</option>@endforeach
                    </select>
                </label>
                <div class="form-grid">
                    <label>Szakember
                        <select name="staff_id"><option value="">Mindegy, kihez</option>
                            @foreach($service?->staff->where('active', true) ?? [] as $person)<option value="{{ $person->id }}" @selected($staffId === $person->id)>{{ $person->name }}</option>@endforeach
                        </select>
                    </label>
                    <label>Nap<input type="date" name="date" value="{{ $date }}" min="{{ now($salon->timezone)->toDateString() }}" max="{{ now($salon->timezone)->addDays($salon->horizon_days)->toDateString() }}" required></label>
                </div>
                <button class="button secondary">Szabad időpontok mutatása <x-icon name="arrow-right" /></button>
            </form>
            <aside class="booking-summary panel" aria-label="Foglalási összesítő">
                <span class="eyebrow">A FOGLALÁSOD</span>
                <h2>{{ $service?->name }}</h2>
                @if($service?->description && ! ($salon->demo && Str::startsWith($service->description, 'Bemutató szolgáltatás')))<p>{{ $service->description }}</p>@endif
                <div class="summary-price">{{ number_format($service?->price ?? 0, 0, ',', ' ') }} Ft{{ $service?->price_kind === 'from' ? '-tól' : '' }}</div>
                <p>{{ $service?->duration_minutes }} perc @if($service?->price_kind === 'indicative') · Tájékoztató ár @endif</p>
                <hr>
                <strong>{{ $salon->name }}</strong>
                <p>{{ $salon->address }}</p>
                <hr>
                <h3 id="booking-terms">Ha közbejön valami</h3>
                <p class="preserve">{{ $salon->terms }}</p>
                @unless($salon->demo)<p class="muted">A visszaigazolást és a foglalásod kezelési linkjét e-mailben küldjük.</p>@endunless
            </aside>
            <form method="post" action="{{ route('booking.store') }}" class="panel booking-submit">
                @csrf
                <input type="hidden" name="service_id" value="{{ $service?->id }}">
                <input type="hidden" name="request_key" value="{{ $requestKey }}">
                <p class="eyebrow">02 · IDŐPONT</p>
                <h2>Mikor érkezel?</h2>
                <p class="booking-date-label">{{ \Carbon\CarbonImmutable::parse($date, $salon->timezone)->locale('hu')->isoFormat('YYYY. MMMM D., dddd') }}</p>
                @include('components.slots')
                <hr>
                <p class="eyebrow">03 · ADATAID</p>
                <h2 id="booking-guest">Hogyan érhetünk el?</h2>
                <p class="selected-slot-note" aria-live="polite" hidden></p>
                <label>Neved<input name="name" autocomplete="name" maxlength="120" value="{{ old('name') }}" required></label>
                <div class="form-grid">
                    <label>E-mail-cím<input type="email" name="email" autocomplete="email" value="{{ old('email') }}" required></label>
                    <label>Telefonszám <span class="muted">(opcionális)</span><input type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" maxlength="40"></label>
                </div>
                <label class="check"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))><span>Megismertem a <a href="#booking-terms">foglalási feltételeket</a> és az <a href="{{ route('privacy') }}" target="_blank" rel="noopener">adatkezelési tájékoztatót</a>.</span></label>
                <p class="muted">A választott időpont mellett láthatod a szakember nevét. A foglalás a véglegesítéskor válik érvényessé.</p>
                <button class="button full" @disabled(!$slots)>Foglalás véglegesítése <x-icon name="arrow-right" /></button>
            </form>
        </div>
    @endif
</div>
@endsection
