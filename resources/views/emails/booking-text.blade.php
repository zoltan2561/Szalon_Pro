{!! $salon->name !!}
{{ ['confirmation'=>'A foglalásod visszaigazolva','changed'=>'Az időpontod módosult','cancelled'=>(($guestCancelled ?? false) ? 'Lemondtad a foglalásodat' : 'A foglalásodat lemondtuk'),'reminder'=>'Hamarosan találkozunk'][$kind] }}

Kedves {!! $booking->guest->name !!}!

{!! $booking->service_name !!}
{{ $booking->starts_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }} ({{ $salon->timezone }})
{!! $booking->staff->name !!} · {{ $booking->duration_minutes }} perc
{!! $salon->address !!}
@include('components.price', ['amount'=>$booking->price, 'kind'=>$booking->price_kind])
@if($booking->price_kind==='indicative')Tájékoztató ár.
@endif
@if($booking->status==='confirmed')
Foglalás kezelése: {!! route('booking.manage', $booking->manage_token) !!}
@endif
Új időpont foglalása: {!! route('booking.rebook.form', $booking->manage_token) !!}
A hivatkozások bizalmasak és {{ $booking->token_expires_at->setTimezone($salon->timezone)->format('Y. m. d.') }} napjáig érvényesek. A megnyitás önmagában nem módosít foglalást.

@if($kind!=='cancelled'){!! $salon->terms !!}@endif

Kérdésed van? {!! $salon->phone !!} · {!! $salon->email !!}
