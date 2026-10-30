{{ $salon->name }} – Szabad időpontot találtunk!

Kedves {{ $offer->entry->guest->name }}!
{{ $offer->entry->service->name }}
{{ $offer->starts_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }} · {{ $offer->staff->name }}

Az ajánlat {{ $offer->expires_at->setTimezone($salon->timezone)->format('H:i') }}-ig érvényes, amíg az időpont szabad. A foglalást külön meg kell erősítened:
{!! route('waitlist.offer', $offer->manage_token) !!}

@if($offer->entry->manage_token)Várólistás kérésed kezelése vagy visszavonása:
{!! route('waitlist.request', $offer->entry->manage_token) !!}
@endif
