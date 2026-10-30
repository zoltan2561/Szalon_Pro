<div class="calendar-week-mobile">
    <nav class="calendar-week-days" aria-label="Hét napjai">
        @foreach($weekDays as $entry)
            @php
                $weekDate = $entry['weekDay']->toDateString();
            @endphp
            <a data-week-day="{{ $weekDate }}" href="{{ route('admin.calendar', ['date' => $weekDate, 'view' => 'day']) }}" @if($weekDate === $date) aria-current="date" @endif aria-label="{{ $entry['weekDay']->locale('hu')->isoFormat('MMMM D., dddd') }}"><span>{{ $entry['weekDay']->locale('hu')->isoFormat('ddd') }}</span><strong>{{ $entry['weekDay']->format('d') }}</strong></a>
        @endforeach
    </nav>
    @foreach($weekDays as $entry)
        @php
            $weekDate = $entry['weekDay']->toDateString();
            $transition = $entry['weekDay']->offset !== $entry['weekDay']->addDay()->offset;
        @endphp
        <section class="calendar-week-day" data-week-panel="{{ $weekDate }}" @if($weekDate !== $date) hidden @endif aria-labelledby="week-day-{{ $weekDate }}">
            <h3 id="week-day-{{ $weekDate }}">{{ $entry['weekDay']->locale('hu')->isoFormat('MMMM D., dddd') }}</h3>
            @forelse($entry['entries']->sortBy(fn (array $event) => $event['item']->starts_at) as $event)
                @php
                    $item = $event['item'];
                    $localStart = $item->starts_at->max($entry['start'])->setTimezone($salon->timezone);
                    $localEnd = $item->ends_at->min($entry['end'])->setTimezone($salon->timezone);
                    $timeRange = $localStart->format($transition ? 'H:i P' : 'H:i').'–'.$localEnd->format($transition ? 'H:i P' : 'H:i');
                @endphp
                @if($event['type'] === 'booking')
                    <a class="calendar-week-entry {{ $item->status }}" href="{{ route('admin.booking', $item) }}"><strong>{{ $timeRange }} · {{ $item->guest->name }}</strong><span>{{ $item->service_name }}</span><small>{{ $item->staff->name }} · {{ $item->visitLabel() }}</small></a>
                @else
                    <div class="calendar-week-entry"><strong>{{ $timeRange }} · {{ $event['type'] === 'google' ? 'Google Naptár foglaltság' : $item->reason }}</strong><small>{{ $event['type'] === 'google' ? $item->connection->staff->name : $item->staff->name }}</small></div>
                @endif
            @empty
                <p class="muted">Erre a napra nincs foglalás vagy blokkolás.</p>
            @endforelse
            @foreach($entry['dayExceptions'] as $exception)<p class="muted">{{ $exception->staff->name }} · {{ $exception->hours ? 'Eltérő munkarend' : 'Szabadnap' }}</p>@endforeach
            @if(auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)<a class="button secondary" href="{{ route('admin.create', ['date' => $weekDate]) }}">+ Új foglalás erre a napra</a>@endif
        </section>
    @endforeach
</div>
