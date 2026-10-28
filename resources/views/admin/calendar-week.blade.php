@php
    $weekDays = [];
    $weekStartHour = 8;
    $weekEndHour = 20;
    foreach (range(0, 6) as $offset) {
        $weekDay = $day->addDays($offset);
        $start = $weekDay->utc();
        $end = $weekDay->addDay()->utc();
        $hourSegments = [];
        for ($tick = $start; $tick->lt($end); $tick = $tick->addHour()) {
            $hourSegments[(int) $tick->setTimezone($salon->timezone)->format('G')][] = [$tick, $tick->addHour()->min($end)];
        }
        $dayBookings = $bookings->filter(fn ($booking) => $booking->starts_at->lt($end) && $booking->ends_at->gt($start));
        $dayBlocks = $blocks->filter(fn ($block) => $block->starts_at->lt($end) && $block->ends_at->gt($start));
        $dayBusy = $googleBusy->filter(fn ($busy) => $busy->starts_at->lt($end) && $busy->ends_at->gt($start));
        $dayExceptions = $exceptions->where('date', $weekDay->toDateString());
        $entries = collect();
        foreach ($dayBookings as $booking) {
            $entries->push(['type' => 'booking', 'item' => $booking]);
        }
        foreach ($dayBlocks as $block) {
            $entries->push(['type' => 'block', 'item' => $block]);
        }
        foreach ($dayBusy as $busy) {
            $entries->push(['type' => 'google', 'item' => $busy]);
        }
        foreach ($entries as $entry) {
            $itemStart = $entry['item']->starts_at;
            $itemEnd = $entry['item']->ends_at;
            $localStart = $itemStart->setTimezone($salon->timezone);
            $localEnd = $itemEnd->setTimezone($salon->timezone);
            $weekStartHour = min($weekStartHour, $itemStart->lt($start) ? 0 : $localStart->hour);
            $weekEndHour = max($weekEndHour, $itemEnd->gte($end) ? 24 : (int) ceil($localEnd->hour + $localEnd->minute / 60));
        }
        foreach ($calendarStaff as $person) {
            $exception = $dayExceptions->firstWhere('staff_id', $person->id);
            $workingHours = $exception ? $exception->hours : ($person->weekly_hours[$weekDay->isoWeekday()] ?? []);
            foreach ($workingHours as $interval) {
                $weekStartHour = min($weekStartHour, (int) substr($interval[0], 0, 2));
                $weekEndHour = max($weekEndHour, (int) ceil((int) substr($interval[1], 0, 2) + (int) substr($interval[1], 3, 2) / 60));
            }
        }
        $weekDays[] = compact('weekDay', 'start', 'end', 'hourSegments', 'dayBookings', 'dayExceptions', 'entries');
    }
    $weekStartHour = max(0, $weekStartHour);
    $weekEndHour = min(24, max($weekStartHour + 1, $weekEndHour));
    $weekDayNames = ['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek', 'Szombat', 'Vasárnap'];
@endphp
<div class="calendar-week-grid calendar-week-grid-timed">
    <p class="calendar-week-help">A hosszabb események minden érintett órában láthatók. Az üres mező önmagában nem jelent foglalható időpontot.</p>
    <div class="calendar-week-scroll" tabindex="0" aria-label="Heti időrács, vízszintesen görgethető">
        <table class="calendar-week-table">
            <thead><tr><th scope="col">Idő</th>
            @foreach($weekDays as $index => $weekData)
                <th scope="col" class="{{ $weekData['weekDay']->toDateString() === now($salon->timezone)->toDateString() ? 'is-today' : '' }}">
                    <a href="{{ route('admin.calendar', ['date' => $weekData['weekDay']->toDateString()]) }}">{{ $weekDayNames[$index] }}<br>{{ $weekData['weekDay']->format('Y. m. d.') }}</a>
                    <small>{{ $weekData['dayBookings']->where('status', '!=', 'cancelled')->count() }} időpont</small>
                    @if($weekData['end']->timestamp - $weekData['start']->timestamp !== 86400)<small>Óraátállítás napja</small>@endif
                    @foreach($weekData['dayExceptions'] as $exception)<small>{{ $exception->staff->name }} · {{ empty($exception->hours) ? 'Zárva' : 'Eltérő munkarend' }}</small>@endforeach
                </th>
            @endforeach
            </tr></thead>
            <tbody>
            @foreach(range($weekStartHour, $weekEndHour - 1) as $hour)
                <tr><th scope="row">{{ sprintf('%02d:00', $hour) }}</th>
                @foreach($weekDays as $weekData)
                    @php
                        $segments = $weekData['hourSegments'][$hour] ?? [];
                        $cellEntries = $weekData['entries']->filter(function ($entry) use ($segments): bool {
                            foreach ($segments as [$segmentStart, $segmentEnd]) {
                                if ($entry['item']->starts_at->lt($segmentEnd) && $entry['item']->ends_at->gt($segmentStart)) {
                                    return true;
                                }
                            }

                            return false;
                        })->sortBy(fn ($entry) => $entry['item']->starts_at->timestamp);
                    @endphp
                    <td>
                        @if(! $segments)
                            <span class="calendar-week-missing">Óraátállítás miatt nincs ilyen óra.</span>
                        @elseif($cellEntries->isEmpty())
                            <span aria-label="Nincs bejegyzés">–</span>
                        @else
                            @foreach($cellEntries as $entry)
                                @php
                                    $item = $entry['item'];
                                    $localStart = $item->starts_at->max($weekData['start'])->setTimezone($salon->timezone);
                                    $localEnd = $item->ends_at->min($weekData['end'])->setTimezone($salon->timezone);
                                    $startText = $localStart->format('H:i');
                                    $endText = $item->ends_at->gte($weekData['end']) ? '24:00' : $localEnd->format('H:i');
                                    if (count($weekData['hourSegments'][(int) $localStart->format('G')] ?? []) > 1) {
                                        $startText .= ' UTC'.$localStart->format('P');
                                    }
                                    if ($item->ends_at->lt($weekData['end']) && count($weekData['hourSegments'][(int) $localEnd->format('G')] ?? []) > 1) {
                                        $endText .= ' UTC'.$localEnd->format('P');
                                    }
                                @endphp
                                @if($entry['type'] === 'booking')
                                    <a class="calendar-week-booking {{ $item->status }}" href="{{ route('admin.booking', $item) }}"><span class="calendar-week-booking-time">{{ $startText }}–{{ $endText }}</span><strong>{{ $item->guest->name }}</strong>@if($salon->featureEnabled('no_show_protection') && $item->guest->no_show_count)<small>Nem jelent meg: {{ $item->guest->no_show_count }}</small>@endif<span>{{ $item->service_name }}</span><small>{{ $item->staff->name }} · {{ $item->label() }}</small></a>
                                @else
                                    <div class="calendar-week-unavailable {{ $entry['type'] === 'google' ? 'google' : '' }}"><strong>{{ $startText }} · {{ $entry['type'] === 'google' ? 'Google-foglaltság' : $item->reason }}</strong><small>Vége: {{ $endText }} · {{ $entry['type'] === 'google' ? $item->connection->staff->name : $item->staff->name }}</small></div>
                                @endif
                            @endforeach
                        @endif
                    </td>
                @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
