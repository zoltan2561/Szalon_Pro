@extends('layouts.admin')
@section('title', request()->routeIs('admin.today') ? 'Mai nap' : 'Naptár')
@section('content')
@php
    $isTodayPage = request()->routeIs('admin.today');
    $isActualToday = $date === now($salon->timezone)->toDateString();
    $calendarStaff = $calendarStaff ?? collect();
    $previousDate = $day->subDays($week ? 7 : 1)->toDateString();
    $followingDate = $day->addDays($week ? 7 : 1)->toDateString();
    $confirmedBookings = $bookings->where('status', 'confirmed');
    $completedBookings = $bookings->where('status', 'completed');
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
            $timelineEndHour = max($timelineEndHour, $localEnd->isAfter($day->addDay()) ? 24 : (int) ceil($localEnd->hour + $localEnd->minute / 60));
        }
    }
    $timelineStartHour = max(0, $timelineStartHour);
    $timelineEndHour = min(24, max($timelineStartHour + 1, $timelineEndHour));
    $timelineHeight = ($timelineEndHour - $timelineStartHour) * 90;
    $timelineStart = $day->setTime($timelineStartHour, 0);
    $nowPosition = $isActualToday ? $timelineStart->diffInMinutes(now($salon->timezone), false) * 1.5 : null;
@endphp

<div class="page-heading calendar-page-heading">
    <div>
        <p class="eyebrow">{{ $day->locale('hu')->isoFormat('YYYY. MMMM D., dddd') }}</p>
        <h1>{{ $isTodayPage && $isActualToday ? 'A mai nap menete' : ($week ? 'Heti naptár' : 'Napi naptár') }}</h1>
        <p class="muted">Foglalások, szünetek és eltérő munkaidő egy helyen.</p>
    </div>
    <div class="calendar-heading-actions">
        @if(auth()->user()->role === 'owner')<a class="calendar-connect-link" href="{{ route('admin.google.index') }}">Google Naptár kapcsolatok →</a>@endif
        @if(auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)<a class="button" href="{{ route('admin.create', ['date' => $date]) }}">+ Új foglalás</a>@endif
    </div>
</div>

@if(! $salon->published && auth()->user()->role === 'owner')<div class="notice">A nyilvános oldal még nincs közzétéve. <a href="{{ route('admin.settings') }}">Induló beállítások →</a></div>@endif
@if($failed)<div class="notice error">{{ $failed }} értesítés küldése figyelmet igényel. <a href="{{ route('admin.notifications') }}">Küldési napló →</a></div>@endif

@if($isTodayPage && $next)
    <section class="next-guest" aria-labelledby="next-guest-heading"><div><p class="eyebrow">A KÖVETKEZŐ VENDÉG</p><h2 id="next-guest-heading">{{ $next->guest->name }}</h2><p>{{ $next->service_name }} · {{ $next->staff->name }}</p><a href="{{ route('admin.booking', $next) }}">Foglalás megnyitása ↗</a></div><div class="next-time">{{ $next->starts_at->setTimezone($salon->timezone)->format('H:i') }}<small>{{ $next->starts_at->setTimezone($salon->timezone)->format('m. d.') }} · {{ $next->duration_minutes }} perc</small></div></section>
@endif

<section class="calendar-summary" aria-label="Időpontok összesítése">
    <article><span>{{ $week ? 'Heti foglalások' : 'Napi foglalások' }}</span><strong>{{ $bookings->whereIn('status', ['confirmed', 'completed', 'no_show'])->count() }}</strong><small>{{ $bookings->where('status', 'cancelled')->count() }} lemondás</small></article>
    <article><span>Visszaigazolt</span><strong>{{ $confirmedBookings->count() }}</strong><small>{{ $isActualToday && ! $week ? $confirmedBookings->filter(fn ($booking) => $booking->starts_at->isAfter(now()))->count().' még hátravan' : 'tervezett időpont' }}</small></article>
    <article><span>Elvégezve</span><strong>{{ $completedBookings->count() }}</strong><small>{{ $bookings->where('status', 'no_show')->count() }} meg nem jelenés</small></article>
    @if($salon->featureEnabled('financials'))<article><span>Elvégzett szolgáltatások összege</span><strong>{{ number_format($completedBookings->sum('actual_amount'), 0, ',', ' ') }} <small>Ft</small></strong><small>Rögzített összeg, nem pénzforgalmi adat</small></article>@endif
</section>

<section class="panel calendar-workspace" aria-labelledby="calendar-heading">
    <div class="calendar-toolbar">
        <div><p class="eyebrow">{{ $week ? 'HÉT ÁTTEKINTÉSE' : 'A NAP MENETE' }}</p><h2 id="calendar-heading">{{ $week ? $day->locale('hu')->isoFormat('MMM D.').' – '.$day->addDays(6)->locale('hu')->isoFormat('MMM D.') : $day->locale('hu')->isoFormat('MMMM D., dddd') }}</h2></div>
        <div class="calendar-controls">
            <nav class="calendar-period-nav" aria-label="Időszak léptetése"><a href="{{ route('admin.calendar', ['date' => $previousDate, 'view' => $week ? 'week' : 'day']) }}" aria-label="Előző {{ $week ? 'hét' : 'nap' }}">←</a><a class="calendar-period-today" href="{{ route('admin.today') }}">Ma</a><a href="{{ route('admin.calendar', ['date' => $followingDate, 'view' => $week ? 'week' : 'day']) }}" aria-label="Következő {{ $week ? 'hét' : 'nap' }}">→</a></nav>
            <nav class="calendar-view-switch" aria-label="Naptárnézet"><a class="{{ $week ? '' : 'active' }}" href="{{ route('admin.calendar', ['date' => $date, 'view' => 'day']) }}" @if(! $week) aria-current="page" @endif>Napi</a><a class="{{ $week ? 'active' : '' }}" href="{{ route('admin.calendar', ['date' => $date, 'view' => 'week']) }}" @if($week) aria-current="page" @endif>Heti</a></nav>
            <form class="calendar-date-form" method="get" action="{{ route('admin.calendar') }}"><label class="sr-only" for="calendar-date">Ugrás egy napra</label><input id="calendar-date" type="date" name="date" value="{{ $date }}"><input type="hidden" name="view" value="{{ $week ? 'week' : 'day' }}"><button class="button secondary small">Ugrás</button></form>
        </div>
    </div>

    @if($week)
        <div class="calendar-week-grid">
            @foreach(range(0, 6) as $offset)
                @php
                    $weekDay = $day->addDays($offset);
                    $weekDayStart = $weekDay->utc();
                    $weekDayEnd = $weekDay->addDay()->utc();
                    $dayBookings = $bookings->filter(fn ($booking) => $booking->starts_at->setTimezone($salon->timezone)->toDateString() === $weekDay->toDateString());
                    $dayBlocks = $blocks->filter(fn ($block) => $block->starts_at->isBefore($weekDayEnd) && $block->ends_at->isAfter($weekDayStart));
                    $dayBusy = $googleBusy->filter(fn ($busy) => $busy->starts_at->isBefore($weekDayEnd) && $busy->ends_at->isAfter($weekDayStart));
                    $dayExceptions = $exceptions->where('date', $weekDay->toDateString());
                @endphp
                <section class="calendar-week-day {{ $weekDay->toDateString() === now($salon->timezone)->toDateString() ? 'is-today' : '' }}" aria-labelledby="week-day-{{ $offset }}">
                    <div class="calendar-week-day-heading"><a id="week-day-{{ $offset }}" href="{{ route('admin.calendar', ['date' => $weekDay->toDateString()]) }}"><span>{{ $weekDay->locale('hu')->isoFormat('ddd') }}</span><strong>{{ $weekDay->format('d') }}</strong></a><small>{{ $dayBookings->where('status', '!=', 'cancelled')->count() }} időpont</small></div>
                    <div class="calendar-week-entries">
                        @foreach($dayBookings as $booking)
                            <a class="calendar-week-booking {{ $booking->status }}" href="{{ route('admin.booking', $booking) }}"><span class="calendar-week-booking-time">{{ $booking->starts_at->setTimezone($salon->timezone)->format('H:i') }}</span><strong>{{ $booking->guest->name }}</strong><span>{{ $booking->service_name }}</span><small>{{ $booking->staff->name }} · {{ $booking->label() }}</small></a>
                        @endforeach
                        @foreach($dayBlocks as $block)<div class="calendar-week-unavailable"><strong>{{ $block->starts_at->setTimezone($salon->timezone)->format('H:i') }} · {{ $block->reason }}</strong><small>{{ $block->staff->name }}</small></div>@endforeach
                        @foreach($dayBusy as $busy)<div class="calendar-week-unavailable google"><strong>{{ $busy->starts_at->setTimezone($salon->timezone)->format('H:i') }} · Google-foglaltság</strong><small>{{ $busy->connection->staff->name }}</small></div>@endforeach
                        @foreach($dayExceptions as $exception)<div class="calendar-week-unavailable closed"><strong>{{ empty($exception->hours) ? 'Zárva' : 'Eltérő munkarend' }}</strong><small>{{ $exception->staff->name }}</small></div>@endforeach
                        @if($dayBookings->isEmpty() && $dayBlocks->isEmpty() && $dayBusy->isEmpty() && $dayExceptions->isEmpty())<p class="calendar-week-empty">Nincs bejegyzés</p>@endif
                    </div>
                </section>
            @endforeach
        </div>
    @else
        <div class="calendar-day-layout">
            <div class="calendar-timeline" aria-label="Szakemberek napi idősávjai">
                @if($calendarStaff->isNotEmpty())
                    <div class="calendar-timeline-scroll"><div class="calendar-timeline-inner" style="min-width: {{ 64 + $calendarStaff->count() * 190 }}px">
                        <div class="calendar-timeline-head" style="grid-template-columns: 64px repeat({{ $calendarStaff->count() }}, minmax(0, 1fr))">
                            <span>Idő</span>
                            @foreach($calendarStaff as $person)
                                @php
                                    $personException = $exceptions->firstWhere('staff_id', $person->id);
                                @endphp
                                <div><strong>{{ $person->name }}</strong>@if($personException)<small>{{ empty($personException->hours) ? 'Zárva' : 'Eltérő munkarend' }}</small>@endif</div>
                            @endforeach
                        </div>
                        <div class="calendar-timeline-body" style="grid-template-columns: 64px repeat({{ $calendarStaff->count() }}, minmax(0, 1fr)); height: {{ $timelineHeight }}px">
                            <div class="calendar-time-rail">
                                @foreach(range($timelineStartHour, $timelineEndHour - 1) as $hour)
                                    <span style="top: {{ ($hour - $timelineStartHour) * 90 }}px">{{ sprintf('%02d:00', $hour) }}</span>
                                @endforeach
                            </div>
                            @foreach($calendarStaff as $person)
                                @php
                                    $personException = $exceptions->firstWhere('staff_id', $person->id);
                                @endphp
                                <div class="calendar-timeline-track {{ $personException && empty($personException->hours) ? 'is-closed' : '' }}">
                                    @foreach($bookings->where('staff_id', $person->id)->where('status', '!=', 'cancelled') as $booking)
                                        @php
                                            $eventStart = $booking->starts_at->setTimezone($salon->timezone);
                                            $eventEnd = $booking->ends_at->setTimezone($salon->timezone);
                                            $eventTop = max(0, $timelineStart->diffInMinutes($eventStart, false) * 1.5);
                                            $eventHeight = max(28, min($timelineHeight - $eventTop, $eventStart->diffInMinutes($eventEnd) * 1.5));
                                        @endphp
                                        <a class="calendar-timeline-event {{ $booking->status }}" href="{{ route('admin.booking', $booking) }}" style="top: {{ $eventTop }}px; height: {{ $eventHeight }}px" aria-label="{{ $eventStart->format('H:i') }} {{ $booking->guest->name }}, {{ $booking->service_name }}, {{ $booking->label() }}"><strong>{{ $eventStart->format('H:i') }} · {{ $booking->guest->name }}</strong>@if($eventHeight >= 50)<small>{{ $booking->service_name }}</small>@endif</a>
                                    @endforeach
                                    @foreach($blocks->where('staff_id', $person->id) as $block)
                                        @php
                                            $eventTop = max(0, $timelineStart->diffInMinutes($block->starts_at->setTimezone($salon->timezone), false) * 1.5);
                                            $eventHeight = max(22, min($timelineHeight - $eventTop, $block->starts_at->diffInMinutes($block->ends_at) * 1.5));
                                        @endphp
                                        <div class="calendar-timeline-unavailable" style="top: {{ $eventTop }}px; height: {{ $eventHeight }}px" title="{{ $block->reason }}"><span>{{ $block->reason }}</span></div>
                                    @endforeach
                                    @foreach($googleBusy->filter(fn ($busy) => $busy->connection->staff_id === $person->id) as $busy)
                                        @php
                                            $eventTop = max(0, $timelineStart->diffInMinutes($busy->starts_at->setTimezone($salon->timezone), false) * 1.5);
                                            $eventHeight = max(22, min($timelineHeight - $eventTop, $busy->starts_at->diffInMinutes($busy->ends_at) * 1.5));
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
                <div class="calendar-agenda-heading"><div><p class="eyebrow">RÉSZLETES NAPI LISTA</p><h3 id="agenda-heading">Időpontok</h3></div><span>{{ $bookings->count() }}</span></div>
                <ol class="calendar-agenda-list">
                    @forelse($bookings as $booking)
                        <li><a class="calendar-agenda-item {{ $booking->status }}" href="{{ route('admin.booking', $booking) }}"><span class="calendar-agenda-time">{{ $booking->starts_at->setTimezone($salon->timezone)->format('H:i') }}<small>{{ $booking->ends_at->setTimezone($salon->timezone)->format('H:i') }}</small></span><span class="calendar-agenda-detail"><strong>{{ $booking->guest->name }}</strong><span>{{ $booking->service_name }} · {{ $booking->duration_minutes }} perc</span><small>{{ $booking->staff->name }} · {{ $booking->label() }}</small></span></a></li>
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
    <div class="calendar-foot"><span>{{ $salon->timezone }} · Online és kézi foglalások</span><a href="{{ route('admin.create', ['date' => $date]) }}">Szabad időpontok keresése ↗</a></div>
</section>
@endsection
