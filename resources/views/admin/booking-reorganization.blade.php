@if($booking->status === 'confirmed' && (auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings))
@php($mode = old('mode', request('mode') === 'swap' ? 'swap' : 'shift_day'))
<details id="booking-reorganize" class="panel booking-reorganization" @if(request('open_reorganization') || $errors->hasAny(['mode', 'shift_minutes', 'swap_booking_id', 'reorganization'])) open @endif>
    <summary><span><strong>Nap átszervezése</strong><small>Több vendég eltolása vagy két időpont cseréje</small></span></summary>
    <p>A módosításokat előbb megmutatjuk. Csak a következő oldalon véglegesíted őket.</p>
    <form method="post" action="{{ route('admin.booking.reorganize.preview', $booking) }}" data-reorganization data-options-url="{{ route('admin.booking.reorganize.options', $booking) }}">@csrf
        <input type="hidden" name="revision" value="{{ $booking->revision }}">
        <fieldset><legend>Mit szeretnél?</legend><label class="check"><input type="radio" name="mode" value="shift_day" @checked($mode === 'shift_day')><span>Ezt és az aznapi későbbi vendégeket is eltolom</span></label><label class="check"><input type="radio" name="mode" value="swap" @checked($mode === 'swap')><span>Megcserélem egy másik foglalással</span></label></fieldset>
        <fieldset data-reorganization-shift><label>Eltolás percben<input name="shift_minutes" type="number" min="-720" max="720" step="15" value="{{ old('shift_minutes', 60) }}"></label><p class="muted">60 = egy órával később, −30 = fél órával korábban. Csak {{ $booking->staff->name }} ezen a napon, {{ $booking->starts_at->setTimezone($salon->timezone)->format('H:i') }}-tól kezdődő, még visszaigazolt foglalásait érinti. A korábbi és más napokra eső foglalások megmaradnak.</p></fieldset>
        <fieldset data-reorganization-swap><legend>Melyik foglalással cserélnéd meg?</legend>
            <label>A másik foglalás napja<input type="date" name="swap_date" value="{{ $swapDate }}" data-swap-date form="swap-date-{{ $booking->id }}"></label>
            <button class="button secondary" type="submit" form="swap-date-{{ $booking->id }}">Nap foglalásainak betöltése</button>
            <p class="muted" role="status" data-swap-status>{{ $swapCandidates->isEmpty() ? 'Ezen a napon nincs másik cserélhető foglalás.' : 'Válassz egy másik vendéget. A két foglalás megtartja a saját szolgáltatását és időtartamát.' }}</p>
            <div class="swap-candidates" data-swap-candidates>@foreach($swapCandidates as $candidate)<label class="swap-candidate"><input type="radio" name="swap_booking_id" value="{{ $candidate->id }}" @checked(old('swap_booking_id') == $candidate->id)><span><strong>{{ $candidate->starts_at->setTimezone($salon->timezone)->format('H:i') }} · {{ $candidate->guest->name }}</strong><small>{{ $candidate->service_name }} · {{ $candidate->duration_minutes }} perc · {{ $candidate->staff->name }}</small></span></label>@endforeach</div>
            <p class="muted">A csere a kezdési időt és a szakembert is felcseréli. Csak akkor menthető, ha mindkét szolgáltatás elvégezhető az új helyen.</p>
        </fieldset>
        <button class="button" data-reorganization-preview>Változások áttekintése</button>
    </form>
    <form id="swap-date-{{ $booking->id }}" action="{{ route('admin.booking', $booking) }}#booking-reorganize" method="get" data-swap-date-form><input type="hidden" name="mode" value="swap"><input type="hidden" name="open_reorganization" value="1"></form>
</details>
@endif
