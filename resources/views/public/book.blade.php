@extends('layouts.public')
@section('title', 'Időpontfoglalás')
@section('content')
@php($contactMode = $salon->featureEnabled('phone_booking') ? $salon->contact_mode : 'email')
@php($referralSource = $salon->featureEnabled('sharing') && in_array(request('utm_source', old('referral_source')), \App\Services\BookingLinks::Sources, true) ? request('utm_source', old('referral_source')) : null)
<div class="booking-wrap wrap">
    <p class="eyebrow">EGY LÉPÉSSEL KÖZELEBB</p>
    <h1 class="page-title">{{ $salon->demo ? $brand['demo_copy']['booking_heading'] : 'A következő énidőd.' }}</h1>
    <ol class="steps"><li>01 <span>Szolgáltatás</span></li><li>02 <span>Időpont</span></li><li>03 <span>Adataid és véglegesítés</span></li></ol>
    @if($salon->demo)<div class="notice demo-booking-notice">Ez egy bemutató. A foglalás kipróbálható, de kérjük, ne adj meg valódi személyes adatot.</div>@endif
    @if(!$ready || !$salon->published)
        <div class="notice">Az online foglalás még nem aktív. Kérjük, keresd a szalont az elérhetőségein.</div>
    @else
        <div class="booking-grid">
            <form method="get" action="{{ $rebookToken ? route('booking.rebook.form', $rebookToken) : route('booking.form') }}" class="panel filter-form">
                <p class="eyebrow">01 · VÁLASSZ</p>
                @if($referralSource)<input type="hidden" name="utm_source" value="{{ $referralSource }}">@endif
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
                @if($salon->featureEnabled('no_show_protection'))<p>Online módosítás és lemondás: legalább {{ $salon->cancel_notice_hours }} órával az időpont előtt. Egyeztetés: <a href="tel:{{ preg_replace('/[^+0-9]/', '', $salon->phone) }}">{{ $salon->phone }}</a>.</p>@endif
                @unless($salon->demo)<p class="muted">A visszaigazolást és a kezelési linket e-mailben, e-mail nélkül beállított SMS-kapcsolat esetén SMS-ben küldjük. A visszaigazoló oldalon is megkapod a linket.</p>@endunless
            </aside>
            <form method="post" action="{{ $rebookToken ? route('booking.rebook.store', $rebookToken) : route('booking.store') }}" class="panel booking-submit">
                @csrf
                <input type="hidden" name="service_id" value="{{ $service?->id }}">
                <input type="hidden" name="request_key" value="{{ $requestKey }}">
                @if($referralSource)<input type="hidden" name="referral_source" value="{{ $referralSource }}">@endif
                <p class="eyebrow">02 · IDŐPONT</p>
                <h2>Mikor érkezel?</h2>
                <p class="booking-date-label">{{ \Carbon\CarbonImmutable::parse($date, $salon->timezone)->locale('hu')->isoFormat('YYYY. MMMM D., dddd') }}</p>
                @include('components.slots')
                <hr>
                <p class="eyebrow">03 · ADATAID</p>
                <h2 id="booking-guest">Hogyan érhetünk el?</h2>
                <p class="selected-slot-note" aria-live="polite" hidden></p>
                @if($rebookingGuest)
                    <p class="muted">A korábbi foglalásod vendégkártyájával foglalunk újra. Elérhetőség módosításához keresd a szalont.</p>
                    <p><strong>{{ $rebookingGuest->name }}</strong><br>{{ $rebookingGuest->email }}{{ $rebookingGuest->phone ? ' · '.$rebookingGuest->phone : '' }}</p>
                    <input type="hidden" name="name" value="{{ $rebookingGuest->name }}"><input type="hidden" name="email" value="{{ $rebookingGuest->email }}"><input type="hidden" name="phone" value="{{ $rebookingGuest->phone }}">
                @else
                    <label>Neved<input name="name" autocomplete="name" maxlength="120" value="{{ old('name') }}" required></label>
                    <div class="form-grid">
                        <label>E-mail-cím @if($contactMode === 'phone')<span class="muted">(opcionális)</span>@endif<input type="email" inputmode="email" name="email" autocomplete="email" value="{{ old('email') }}" @required($contactMode !== 'phone')></label>
                        <label>Telefonszám @if($contactMode === 'email')<span class="muted">(opcionális)</span>@endif<input type="tel" inputmode="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" maxlength="40" @required($contactMode !== 'email')></label>
                    </div>
                @endif
                @if($smsReady && (!$rebookingGuest || $rebookingGuest->phone))<label class="check"><input type="checkbox" name="sms_opt_in" value="1" @checked(old('sms_opt_in'))><span>SMS-emlékeztetőt kérek a megadott telefonszámra.</span></label>@endif
                @if($salon->featureEnabled('reviews'))<label class="check"><input type="checkbox" name="review_opt_in" value="1" @checked(old('review_opt_in'))><span>Kérek a látogatás után egy semleges értékeléskérő e-mailt vagy SMS-t a szalontól, legfeljebb 90 naponta. Bármikor leiratkozhatok; a foglalásom ettől független.</span></label>@endif
                <label class="check"><input type="checkbox" name="terms" value="1" required @checked(old('terms'))><span>Megismertem a <a href="#booking-terms">foglalási feltételeket</a> és az <a href="{{ route('privacy') }}" target="_blank" rel="noopener">adatkezelési tájékoztatót</a>.</span></label>
                <p class="muted">A választott időpont mellett láthatod a szakember nevét. A foglalás a véglegesítéskor válik érvényessé.</p>
                @if($contactMode === 'phone' && $smsReady)<p class="muted">Ha e-mailt nem adsz meg, a foglalásról és módosításáról SMS-t küldünk a megadott számra. Az emlékeztetőt külön kérheted.</p>@endif
                <button class="button full booking-finalize" @disabled(!$slots)>Foglalás véglegesítése <x-icon name="arrow-right" /></button>
            </form>
        </div>
    @endif
</div>
@endsection
