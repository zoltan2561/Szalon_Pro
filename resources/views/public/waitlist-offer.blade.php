@extends('layouts.public')
@section('title', 'Szabad időpontot találtunk')
@section('content')
<div class="wrap narrow"><p class="eyebrow">A VÁRÓLISTÁRÓL</p><h1>{{ $available ? 'Jó hír: lett szabad hely!' : 'Ez az ajánlat már nem foglalható' }}</h1>
<section class="panel"><h2>{{ $offer->entry->service->name }}</h2><p><strong>{{ $offer->starts_at->setTimezone($salon->timezone)->locale('hu')->isoFormat('YYYY. MMMM D., dddd · HH:mm') }}</strong></p><p>{{ $offer->staff->name }} · {{ $offer->entry->service->duration_minutes }} perc</p><p>@include('components.price', ['amount' => $offer->entry->service->price, 'kind' => $offer->entry->service->price_kind])</p>
@if($available)<p>Az ajánlatot {{ $offer->expires_at->setTimezone($salon->timezone)->format('H:i') }}-ig fogadhatod el, amíg az időpont szabad. A helyet a megerősítés foglalja le.</p><p class="preserve">{{ $salon->terms }}</p><form method="post" action="{{ route('waitlist.accept', $token) }}">@csrf<label class="check"><input type="checkbox" name="terms" value="1" required><span>Megismertem a foglalási feltételeket és az <a href="{{ route('privacy') }}">adatkezelési tájékoztatót</a>.</span></label><button class="button full">Ezt az időpontot lefoglalom</button></form>@else<p>{{ $offer->status === 'accepted' ? 'Ezt az időpontot már lefoglaltad. A kezelési linket a visszaigazolásban találod.' : 'Nem készült új foglalás. Ha a várólistás kérésed aktív, továbbra is keresünk megfelelő időpontot.' }}</p>@endif
</section><a class="button secondary" href="{{ route('booking.form') }}">Másik időpontot keresek</a></div>
@endsection
