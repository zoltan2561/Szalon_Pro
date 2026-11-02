<div class="calendar-toolbar">
    <div class="calendar-toolbar-heading">
        <div><p class="eyebrow">{{ $week ? 'HÉT ÁTTEKINTÉSE' : 'A NAP MENETE' }}</p><h2 id="calendar-heading">{{ $week ? $day->format('Y. m. d.').' – '.$day->addDays(6)->format('Y. m. d.') : $day->format('Y. m. d.').' · '.$day->locale('hu')->isoFormat('dddd') }}</h2></div>
        <nav class="calendar-view-switch" aria-label="Naptárnézet"><a class="{{ $week ? '' : 'active' }}" href="{{ route('admin.calendar', ['date' => $date, 'view' => 'day'] + $calendarFilter) }}" @if(! $week) aria-current="page" @endif>Napi</a><a class="{{ $week ? 'active' : '' }}" href="{{ route('admin.calendar', ['date' => $date, 'view' => 'week'] + $calendarFilter) }}" @if($week) aria-current="page" @endif>Heti</a></nav>
    </div>
    <div class="calendar-controls">
        <nav class="calendar-period-nav" aria-label="Időszak léptetése"><a href="{{ route('admin.calendar', ['date' => $previousDate, 'view' => $week ? 'week' : 'day'] + $calendarFilter) }}" aria-label="Előző {{ $week ? 'hét' : 'nap' }}">←</a><a class="calendar-period-today" href="{{ route('admin.today', ['view' => $week ? 'week' : 'day'] + $calendarFilter) }}">Ma</a><a href="{{ route('admin.calendar', ['date' => $followingDate, 'view' => $week ? 'week' : 'day'] + $calendarFilter) }}" aria-label="Következő {{ $week ? 'hét' : 'nap' }}">→</a></nav>
        <details class="calendar-filter-panel" data-calendar-filters open>
        <summary><span>Dátum és munkatárs</span><small>{{ $selectedStaffId ? $staffOptions->firstWhere('id', $selectedStaffId)?->name : 'Minden szakember' }}</small></summary>
        <form class="calendar-date-form" data-auto-filter="calendar" method="get" action="{{ route($calendarRoute) }}">
            <div class="calendar-filter-field calendar-filter-date"><label for="calendar-date">Dátum</label><input id="calendar-date" type="date" name="date" value="{{ $date }}"></div>
            <input type="hidden" name="view" value="{{ $week ? 'week' : 'day' }}">
            @if(auth()->user()->role === 'owner')
                <div class="calendar-filter-field"><label for="calendar-staff">Munkatárs</label><select id="calendar-staff" name="staff_id"><option value="">Minden szakember</option>@foreach($staffOptions as $person)<option value="{{ $person->id }}" @selected($selectedStaffId === $person->id)>{{ $person->name }}</option>@endforeach</select></div>
            @elseif($selectedStaffId)
                <input type="hidden" name="staff_id" value="{{ $selectedStaffId }}">
            @endif
            <button class="button secondary small">Ugrás</button>
        </form>
        </details>
    </div>
</div>
