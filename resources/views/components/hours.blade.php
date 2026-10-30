@php
    $days = [1=>'Hétfő',2=>'Kedd',3=>'Szerda',4=>'Csütörtök',5=>'Péntek',6=>'Szombat',7=>'Vasárnap'];
    $restoreHours = old('_hours_context') === ($hoursContext ?? 'salon') && session()->hasOldInput('hours');
@endphp
<div class="hours-table"><div class="hours-head"><span>Nap</span><span>Kezdés</span><span>Zárás</span><span>Szünet eleje</span><span>Szünet vége</span><span>Zárva</span></div>
@foreach($days as $day=>$label)
    @php
        $ranges = $hours[$day] ?? [];
        $row = $restoreHours ? old('hours.'.$day, []) : [
            'start' => $ranges[0][0] ?? '',
            'end' => count($ranges)>1 ? $ranges[1][1] : ($ranges[0][1] ?? ''),
            'break_start' => count($ranges)>1 ? $ranges[0][1] : '',
            'break_end' => count($ranges)>1 ? $ranges[1][0] : '',
            'closed' => !$ranges,
        ];
    @endphp
    <div class="hours-row"><strong>{{ $label }}</strong><label class="hours-time"><span>Kezdés</span><input aria-label="{{ $label }} kezdés" type="time" name="hours[{{ $day }}][start]" value="{{ $row['start'] ?? '' }}"></label><label class="hours-time"><span>Zárás</span><input aria-label="{{ $label }} zárás" type="time" name="hours[{{ $day }}][end]" value="{{ $row['end'] ?? '' }}"></label><label class="hours-time"><span>Szünet eleje</span><input aria-label="{{ $label }} szünet eleje" type="time" name="hours[{{ $day }}][break_start]" value="{{ $row['break_start'] ?? '' }}"></label><label class="hours-time"><span>Szünet vége</span><input aria-label="{{ $label }} szünet vége" type="time" name="hours[{{ $day }}][break_end]" value="{{ $row['break_end'] ?? '' }}"></label><label class="check hours-closed"><input aria-label="{{ $label }} zárva" type="checkbox" name="hours[{{ $day }}][closed]" value="1" @checked(!empty($row['closed']))><span>Szabadnap</span></label></div>
@endforeach
</div>
