{!! $salon->name !!}

Új foglalás érkezett

Vendég: {!! $booking->guest->name !!}
Időpont: {{ $booking->starts_at->setTimezone($salon->timezone)->locale('hu')->isoFormat('YYYY. MMMM D., dddd HH:mm') }} ({{ $salon->timezone }})
Szolgáltatás: {!! $booking->service_name !!} · {{ $booking->duration_minutes }} perc
Szakember: {!! $booking->staff->name !!}

Foglalás megnyitása: {!! route('admin.booking', $booking) !!}
A részletek a munkafelületen, belépés után érhetők el.
