@extends('layouts.admin')
@section('title', request()->routeIs('admin.today') ? 'Mai nap' : 'Naptár')
@section('content')
@php
    $isTodayPage = request()->routeIs('admin.today');
    $isActualToday = $date === now($salon->timezone)->toDateString();
    $calendarStaff = $calendarStaff ?? collect();
    $previousDate = $day->subDays($week ? 7 : 1)->toDateString();
    $followingDate = $day->addDays($week ? 7 : 1)->toDateString();
    $summaryBookings = $week ? $bookings->filter(fn ($booking) => $booking->starts_at->gte($day->utc())) : $bookings;
    $confirmedBookings = $summaryBookings->where('status', 'confirmed');
    $completedBookings = $summaryBookings->where('status', 'completed');
    $timelineStartHour = 8;
    $timelineEndHour = 20;

    if (! $week) {
        foreach ($calendarStaff as $person) {
            $workingHours = $exceptions->firstWhere('staff_id', $person->id)?->hours ?? ($person->weekly_hours[$day->dayOfWeekIso] ?? []);
            foreach ($workingHours as $interval) {
                $timelineStartHour = min($timelineStartHour, (int) substr($interval[0], 0, 2));
                $timelineEndHour = max($timelineEndHour, (int) ceil((int) substr($interval[1], 0, 2) + (int) substr($interval[1], 3, 2) / 60));
            }
        }
        foreach ($bookings->concat($blocks)->concat($googleBusy) as $item) {
            $localStart = $item->starts_at->setTimezone($salon->timezone);
            $localEnd = $item->ends_at->setTimezone($salon->timezone);
            $timelineStartHour = min($timelineStartHour, $localStart->isBefore($day) ? 0 : $localStart->hour);
            $timelineEndHour = max($timelineEndHour, $localEnd->greaterThanOrEqualTo($day->addDay()) ? 24 : (int) ceil($localEnd->hour + $localEnd->minute / 60));
        }
    }
    $timelineStartHour = max(0, $timelineStartHour);
    $timelineEndHour = min(24, max($timelineStartHour + 1, $timelineEndHour));
    $timelineStart = $day->setTime($timelineStartHour, 0);
    $timelineEnd = $timelineEndHour === 24 ? $day->addDay() : $day->setTime($timelineEndHour, 0);
    $timelineHeight = $timelineStart->diffInMinutes($timelineEnd) * 1.5;
    $timelineTicks = collect();
    for ($tick = $timelineStart->utc(); $tick->isBefore($timelineEnd); $tick = $tick->addHour()) {
        $timelineTicks->push($tick->setTimezone($salon->timezone));
    }
    $repeatedTickLabels = $timelineTicks->map(fn ($tick) => $tick->format('H:i'))->countBy();
    $nowPosition = $isActualToday ? $timelineStart->diffInMinutes(now($salon->timezone), false) * 1.5 : null;
@endphp

<div class="page-heading calendar-page-heading">
    <div>
        <p class="eyebrow">{{ $day->format('Y. m. d.') }} · {{ $day->locale('hu')->isoFormat('dddd') }}</p>
        <h1>{{ $isTodayPage && $isActualToday ? 'A mai nap menete' : ($week ? 'Heti naptár' : 'Napi naptár') }}</h1>
        <p class="muted">Foglalások, szünetek és eltérő munkaidő egy helyen.</p>
    </div>
    <div class="calendar-heading-actions">
        @if(auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)<a class="button" href="{{ route('admin.create', ['date' => $date]) }}">+ Új foglalás</a>@endif
    </div>
</div>

@if($isTodayPage && (! $salon->published && auth()->user()->role === 'owner' || $failed))
<details class="notice compact-warning"><summary>Figyelem · {{ (int) (! $salon->published && auth()->user()->role === 'owner') + (int) ($failed > 0) }} probléma</summary>
@if(! $salon->published && auth()->user()->role === 'owner')<p>A nyilvános oldal még nincs közzétéve. <a href="{{ route('admin.settings') }}">Induló beállítások →</a></p>@endif
@if($failed)<p>{{ $failed }} értesítés küldése figyelmet igényel. <a href="{{ route('admin.notifications') }}">Küldési napló →</a></p>@endif
</details>
@elseif(! $isTodayPage)
@if(! $salon->published && auth()->user()->role === 'owner')<div class="notice">A nyilvános oldal még nincs közzétéve. <a href="{{ route('admin.settings') }}">Induló beállítások →</a></div>@endif
@if($failed)<div class="notice error">{{ $failed }} értesítés küldése figyelmet igényel. <a href="{{ route('admin.notifications') }}">Küldési napló →</a></div>@endif
@endif

@if($isTodayPage && $next)
    @php
        $nextLocal = $next->starts_at->setTimezone($salon->timezone);
        $todayLocal = now($salon->timezone)->toDateString();
        $nextDayLabel = $nextLocal->toDateString() === $todayLocal ? 'Ma' : ($nextLocal->toDateString() === now($salon->timezone)->addDay()->toDateString() ? 'Holnap' : $nextLocal->format('Y. m. d.'));
        $nextPhone = preg_replace('/[^+0-9]/', '', (string) $next->guest->phone);
    @endphp
    <section class="next-guest" aria-labelledby="next-guest-heading"><div><p class="eyebrow">KÖVETKEZŐ VENDÉG · {{ $nextDayLabel }}</p><h2 id="next-guest-heading">{{ $next->guest->name }}</h2><p>{{ $next->service_name }} · {{ $next->staff->name }}</p><div class="next-guest-actions"><a href="{{ route('admin.booking', $next) }}">Foglalás megnyitása ↗</a>@if($nextPhone)<a href="tel:{{ $nextPhone }}" aria-label="{{ $next->guest->name }} hívása">Vendég hívása</a>@endif</div></div><div class="next-time">{{ $nextLocal->format('H:i') }}<small>{{ $nextLocal->format('Y. m. d.') }} · {{ $next->duration_minutes }} perc</small></div></section>
@elseif($isTodayPage)
    <section class="next-guest" aria-labelledby="next-guest-heading"><div><p class="eyebrow">A KÖVETKEZŐ VENDÉG</p><h2 id="next-guest-heading">Nincs következő foglalás</h2><p>Az új időpontok itt jelennek meg.</p></div></section>
@endif

@if($isTodayPage && (auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings))
<div class="quick-actions">
<details class="quick-sheet" data-quick-sheet @if(old('quick_time')) open @endif><summary class="button">Gyors foglalás</summary><form method="post" action="{{ route('admin.store') }}" data-quick-preview-url="{{ route('admin.create') }}">@csrf
<input type="hidden" name="request_key" value="{{ $requestKey }}">
<input type="hidden" name="preview_token" value="">
<label>Szolgáltatás<select name="service_id" required>@foreach($quickServices as $service)<option value="{{ $service->id }}" @selected(old('service_id') == $service->id)>{{ $service->name }} · {{ $service->duration_minutes }} perc</option>@endforeach</select></label>
<label>Nap kiválasztása <span class="muted">(opcionális)</span><input type="date" name="quick_date" value="{{ old('quick_date') }}" min="{{ now($salon->timezone)->toDateString() }}" max="{{ now($salon->timezone)->addDays($salon->horizon_days)->toDateString() }}"></label>
<label data-quick-time-mode>Időpont<select name="quick_time"><option value="now" @selected(old('quick_time') === 'now')>Most · első szabad ma</option><option value="plus15" @selected(old('quick_time') === 'plus15')>+15 perc · első szabad ma</option><option value="nearest" @selected(old('quick_time') === 'nearest')>Legközelebbi szabad időpont</option></select></label>
<label data-quick-slots-label hidden>Szabad időpontok a kiválasztott napon<select data-quick-slots aria-describedby="quick-preview-status"></select></label>
<label>Vendég neve<input name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120"></label>
<details class="quick-contact" @if(old('phone')) open @endif><summary>Telefonszám megadása (opcionális)</summary><label>Telefonszám<input name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" maxlength="40"></label></details>
<p id="quick-preview-status" class="quick-preview" data-quick-preview aria-live="polite">A választott szabad időpontot itt mutatjuk meg.</p><button class="button" type="submit" disabled>Foglalás mentése</button><noscript><p>Az előnézethez JavaScript szükséges. <a href="{{ route('admin.create') }}">Foglalás az időpontlistából</a></p></noscript></form></details>
@if(auth()->user()->role === 'owner')<details class="quick-sheet"><summary class="button secondary">Idő blokkolása vagy szabadság</summary>
<form method="post" action="{{ route('admin.block') }}">@csrf<h2>Idősáv blokkolása</h2><label>Szakember<select name="staff_id">@foreach($calendarStaff->where('active', true) as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label><label>Ettől<input type="datetime-local" name="starts_at" required></label><label>Eddig<input type="datetime-local" name="ends_at" required></label><label>Megnevezés<input name="reason" value="Szünet" required maxlength="120"></label><button class="button">Blokkolás mentése</button></form>
<form method="post" action="{{ route('admin.exception') }}">@csrf<h2>Szabadság</h2><label>Szakember<select name="staff_id">@foreach($calendarStaff->where('active', true) as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label><label>Nap<input type="date" name="date" value="{{ $date }}" required></label><input type="hidden" name="closed" value="1"><button class="button secondary">Szabadság mentése</button></form></details>@endif
</div>
@endif

<div class="calendar-overview"><section class="calendar-summary" aria-label="Időpontok összesítése">
    <article><span>{{ $week ? 'Heti foglalások' : 'Napi foglalások' }}</span><strong>{{ $summaryBookings->whereIn('status', ['confirmed', 'completed', 'no_show'])->count() }}</strong><small>Lemondások nélkül · {{ $summaryBookings->where('status', 'cancelled')->count() }} lemondás</small></article>
    <article><span>Visszaigazolt</span><strong>{{ $confirmedBookings->count() }}</strong><small>{{ $isActualToday && ! $week ? $confirmedBookings->filter(fn ($booking) => $booking->starts_at->isAfter(now()))->count().' még hátravan' : 'tervezett időpont' }}</small></article>
    <article><span>Elvégezve</span><strong>{{ $completedBookings->count() }}</strong><small>{{ $summaryBookings->where('status', 'no_show')->count() }} meg nem jelenés</small></article>
    @if($salon->featureEnabled('financials'))<article><span>Elvégzett szolgáltatások összege</span><strong>{{ number_format($completedBookings->sum('actual_amount'), 0, ',', ' ') }} <small>Ft</small></strong><small>Rögzített összeg, nem pénzforgalmi adat</small></article>@endif
</section>

<section class="panel calendar-workspace" aria-labelledby="calendar-heading">
    <div class="calendar-toolbar">
        <div><p class="eyebrow">{{ $week ? 'HÉT ÁTTEKINTÉSE' : 'A NAP MENETE' }}</p><h2 id="calendar-heading">{{ $week ? $day->format('Y. m. d.').' – '.$day->addDays(6)->format('Y. m. d.') : $day->format('Y. m. d.').' · '.$day->locale('hu')->isoFormat('dddd') }}</h2></div>
        <div class="calendar-controls">
            <nav class="calendar-period-nav" aria-label="Időszak léptetése"><a href="{{ route('admin.calendar', ['date' => $previousDate, 'view' => $week ? 'week' : 'day']) }}" aria-label="Előző {{ $week ? 'hét' : 'nap' }}">←</a><a class="calendar-period-today" href="{{ route('admin.today') }}">Ma</a><a href="{{ route('admin.calendar', ['date' => $followingDate, 'view' => $week ? 'week' : 'day']) }}" aria-label="Következő {{ $week ? 'hét' : 'nap' }}">→</a></nav>
            <nav class="calendar-view-switch" aria-label="Naptárnézet"><a class="{{ $week ? '' : 'active' }}" href="{{ route('admin.calendar', ['date' => $date, 'view' => 'day']) }}" @if(! $week) aria-current="page" @endif>Napi</a><a class="{{ $week ? 'active' : '' }}" href="{{ route('admin.calendar', ['date' => $date, 'view' => 'week']) }}" @if($week) aria-current="page" @endif>Heti</a></nav>
            <form class="calendar-date-form" data-auto-filter="calendar" method="get" action="{{ route('admin.calendar') }}"><label class="sr-only" for="calendar-date">Ugrás egy napra</label><input id="calendar-date" type="date" name="date" value="{{ $date }}"><input type="hidden" name="view" value="{{ $week ? 'week' : 'day' }}"><button class="button secondary small">Ugrás</button></form>
        </div>
    </div>

    @if($week)
        @include('admin.calendar-week')
    @else
        <div class="calendar-day-layout">
            <div class="calendar-timeline" aria-label="Szakemberek napi idősávjai">
                @if($calendarStaff->isNotEmpty())
                    <div class="calendar-timeline-scroll" tabindex="0" aria-label="Idővonal"><div class="calendar-timeline-inner" style="min-width: {{ 64 + $calendarStaff->count() * 190 }}px">
                        <div class="calendar-timeline-head" style="grid-template-columns: 64px repeat({{ $calendarStaff->count() }}, minmax(0, 1fr))">
                            <span>Idő</span>
                            @foreach($calendarStaff as $person)
                                @php
                                    $personException = $exceptions->firstWhere('staff_id', $person->id);
                                @endphp
                                <div><strong>{{ $person->name }}</strong>@if($personException)<small>{{ empty($personException->hours) ? 'Zárva' : 'Eltérő munkarend' }}</small>@endif @if($person->active && (auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings))<small><a href="{{ route('admin.create', ['date' => $date, 'staff_id' => $person->id]) }}">Új foglalás</a></small>@endif</div>
                            @endforeach
                        </div>
                        <div class="calendar-timeline-body" style="grid-template-columns: 64px repeat({{ $calendarStaff->count() }}, minmax(0, 1fr)); height: {{ $timelineHeight }}px">
                            <div class="calendar-time-rail">
                                @foreach($timelineTicks as $tick)
                                    <span style="top: {{ $timelineStart->diffInMinutes($tick) * 1.5 }}px">{{ $tick->format('H:i') }}@if($repeatedTickLabels[$tick->format('H:i')] > 1)<small style="display: block; font-size: 9px">UTC{{ $tick->format('P') }}</small>@endif</span>
                                @endforeach
                            </div>
                            @foreach($calendarStaff as $person)
                                @php
                                    $personException = $exceptions->firstWhere('staff_id', $person->id);
                                @endphp
                                <div class="calendar-timeline-track {{ $personException && empty($personException->hours) ? 'is-closed' : '' }}" @if($person->active && (auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)) data-calendar-create="{{ route('admin.create', ['date' => $date, 'staff_id' => $person->id]) }}" data-calendar-start="{{ $timelineStart->utc()->format('Y-m-d\TH:i:sP') }}" title="Üres idősávra kattintva új foglalás indítható" @endif>
                                    @foreach($bookings->where('staff_id', $person->id)->where('status', '!=', 'cancelled') as $booking)
                                        @php
                                            $eventStart = $booking->starts_at->setTimezone($salon->timezone);
                                            $eventEnd = $booking->ends_at->setTimezone($salon->timezone);
                                            $eventTop = max(0, $timelineStart->diffInMinutes($eventStart, false) * 1.5);
                                            $eventHeight = max(28, min($timelineHeight - $eventTop, $eventStart->diffInMinutes($eventEnd) * 1.5));
                                        @endphp
                                        <a class="calendar-timeline-event {{ $booking->status }}" href="{{ route('admin.booking', $booking) }}" style="top: {{ $eventTop }}px; height: {{ $eventHeight }}px" aria-label="{{ $eventStart->format('H:i') }} {{ $booking->guest->name }}, {{ $booking->service_name }}, {{ $booking->label() }}"><strong>{{ $eventStart->format('H:i') }} · {{ $booking->guest->name }}</strong>@if($salon->featureEnabled('no_show_protection') && $booking->guest->no_show_count)<small>Nem jelent meg: {{ $booking->guest->no_show_count }}</small>@endif @if($eventHeight >= 50)<small>{{ $booking->service_name }}</small>@endif</a>
                                    @endforeach
                                    @foreach($blocks->where('staff_id', $person->id) as $block)
                                        @php
                                            $eventStart = $block->starts_at->setTimezone($salon->timezone)->max($day);
                                            $eventEnd = $block->ends_at->setTimezone($salon->timezone)->min($day->addDay());
                                            $eventTop = max(0, $timelineStart->diffInMinutes($eventStart, false) * 1.5);
                                            $eventHeight = min($timelineHeight - $eventTop, max(22, $eventStart->diffInMinutes($eventEnd) * 1.5));
                                        @endphp
                                        <div class="calendar-timeline-unavailable" style="top: {{ $eventTop }}px; height: {{ $eventHeight }}px" title="{{ $block->reason }}"><span>{{ $block->reason }}</span></div>
                                    @endforeach
                                    @foreach($googleBusy->filter(fn ($busy) => $busy->connection->staff_id === $person->id) as $busy)
                                        @php
                                            $eventStart = $busy->starts_at->setTimezone($salon->timezone)->max($day);
                                            $eventEnd = $busy->ends_at->setTimezone($salon->timezone)->min($day->addDay());
                                            $eventTop = max(0, $timelineStart->diffInMinutes($eventStart, false) * 1.5);
                                            $eventHeight = min($timelineHeight - $eventTop, max(22, $eventStart->diffInMinutes($eventEnd) * 1.5));
                                        @endphp
                                        <div class="calendar-timeline-unavailable google" style="top: {{ $eventTop }}px; height: {{ $eventHeight }}px" title="Google Naptár foglaltság"><span>Google-foglaltság</span></div>
                                    @endforeach
                                </div>
                            @endforeach
                            @if($nowPosition !== null && $nowPosition >= 0 && $nowPosition <= $timelineHeight)<div class="calendar-now-line" style="top: {{ $nowPosition }}px"><span>Most</span></div>@endif
                        </div>
                    </div></div>
                @else
                    <div class="empty"><h3>Még nincs munkatárs.</h3><p>Vegyél fel munkatársat a naptár használatához.</p></div>
                @endif
            </div>
            <section class="calendar-agenda" aria-labelledby="agenda-heading">
                <div class="calendar-agenda-heading"><div><p class="eyebrow">RÉSZLETES NAPI LISTA · MINDEN ÁLLAPOT</p><h3 id="agenda-heading">Időpontok</h3></div><span>{{ $bookings->count() }}</span></div>
                <ol class="calendar-agenda-list">
                    @forelse($bookings as $booking)
                        <li><a class="calendar-agenda-item {{ $booking->status }}" href="{{ route('admin.booking', $booking) }}"><span class="calendar-agenda-time">{{ $booking->starts_at->setTimezone($salon->timezone)->format('H:i') }}<small>{{ $booking->ends_at->setTimezone($salon->timezone)->format('H:i') }}</small></span><span class="calendar-agenda-detail"><strong>{{ $booking->guest->name }}</strong>@if($salon->featureEnabled('no_show_protection') && $booking->guest->no_show_count)<small>Nem jelent meg: {{ $booking->guest->no_show_count }}</small>@endif<span>{{ $booking->service_name }} · {{ $booking->duration_minutes }} perc</span><small>{{ $booking->staff->name }} · {{ $booking->label() }}</small></span></a></li>
                    @empty<li class="calendar-agenda-empty"><strong>Még nincs foglalás.</strong><span>Az új időpontok itt jelennek meg.</span></li>@endforelse
                </ol>
            </section>
        </div>
    @endif

    @if($blocks->isNotEmpty() || $exceptions->isNotEmpty() || $googleBusy->isNotEmpty())
        <section class="calendar-unavailable" aria-labelledby="calendar-unavailable-heading"><div class="calendar-unavailable-heading"><div><p class="eyebrow">MUNKAIDŐ ÉS FOGLALTSÁG</p><h3 id="calendar-unavailable-heading">Blokkolt és eltérő munkaidő</h3></div>@if(auth()->user()->role === 'owner')<a href="{{ route('admin.staff') }}">Munkarend módosítása →</a>@endif</div>
            <div class="calendar-unavailable-list">
                @foreach($blocks as $block)<div class="calendar-unavailable-item"><strong>{{ $block->starts_at->setTimezone($salon->timezone)->format('m. d. H:i') }}–{{ $block->ends_at->setTimezone($salon->timezone)->format('m. d. H:i') }}</strong><span>{{ $block->staff->name }} · {{ $block->reason }}</span>@if($bookings->contains(fn ($booking) => $booking->staff_id === $block->staff_id && $booking->status === 'confirmed' && $booking->busy_from < $block->ends_at && $booking->busy_until > $block->starts_at))<small class="calendar-conflict">Átfedő foglalás – ellenőrizd</small>@endif</div>@endforeach
                @foreach($googleBusy as $busy)<div class="calendar-unavailable-item google"><strong>{{ $busy->starts_at->setTimezone($salon->timezone)->format('m. d. H:i') }}–{{ $busy->ends_at->setTimezone($salon->timezone)->format('m. d. H:i') }}</strong><span>{{ $busy->connection->staff->name }} · Google Naptár foglaltság</span>@if($bookings->contains(fn ($booking) => $booking->staff_id === $busy->connection->staff_id && $booking->status === 'confirmed' && $booking->busy_from < $busy->ends_at && $booking->busy_until > $busy->starts_at))<small class="calendar-conflict">Átfedő foglalás – ellenőrizd</small>@endif</div>@endforeach
                @foreach($exceptions as $exception)<div class="calendar-unavailable-item closed"><strong>{{ $exception->date }}</strong><span>{{ $exception->staff->name }} · {{ empty($exception->hours) ? 'Zárva' : 'Eltérő munkarend' }}</span>@foreach($exception->hours as $hours)<small>{{ $hours[0] }}–{{ $hours[1] }}</small>@endforeach</div>@endforeach
            </div>
        </section>
    @endif
    <div class="calendar-foot"><span>{{ $salon->timezone === 'Europe/Budapest' ? 'Magyar idő' : $salon->timezone }} · Online, telefonos és személyes foglalások</span><a href="{{ route('admin.create', ['date' => $date]) }}">Szabad időpontok keresése ↗</a></div>
</section>
</div>
@endsection
