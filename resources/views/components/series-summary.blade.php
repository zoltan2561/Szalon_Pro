@if(($seriesBookings ?? collect())->count() > 1)
<details class="panel series-summary" @if(request()->routeIs('booking.success')) open @endif><summary>Rendszeres időpontok · {{ $seriesBookings->count() }} alkalom</summary>
    <ul>@foreach($seriesBookings as $visit)<li><div><strong>{{ $visit->starts_at->setTimezone($salon->timezone)->locale('hu')->isoFormat('MMMM D., dddd · HH:mm') }}</strong><small>{{ $visit->staff->name }} · {{ $visit->label() }}</small></div>
        @if(request()->is('admin/*'))<a href="{{ route('admin.booking', $visit) }}">Megnyitás</a>@elseif($visit->manage_token)<a href="{{ route('booking.manage', $visit->manage_token) }}">Kezelés</a>@endif
    </li>@endforeach</ul>
    @if(request()->is('admin/*') && $booking->status === 'confirmed' && $booking->starts_at->isFuture() && (auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings))
        <form method="post" action="{{ route('admin.booking.update', $booking) }}">@csrf<input type="hidden" name="action" value="cancel"><input type="hidden" name="revision" value="{{ $booking->revision }}"><input type="hidden" name="repeat_scope" value="following"><button class="button danger" data-confirm-cancel data-confirm-message="Biztosan lemondod ezt és a sorozat összes későbbi, még visszaigazolt időpontját?">Hátralévő alkalmak lemondása</button></form>
    @endif
</details>
@endif
