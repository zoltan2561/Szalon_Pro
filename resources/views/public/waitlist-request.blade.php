@extends('layouts.public')
@section('title', 'Várólistás kérésem')
@section('content')
<div class="wrap narrow"><p class="eyebrow">A KÉRÉSED</p><h1>{{ $entry->status === 'waiting' ? 'A várólistán vagy' : 'A kérésed lezárult' }}</h1><section class="panel"><h2>{{ $entry->service->name }}</h2><p>{{ $entry->staff?->name ?? 'Bármelyik alkalmas szakember' }}</p><p>{{ $entry->preferred_date?->format('Y. m. d.') }} – {{ $entry->preferred_until?->format('Y. m. d.') }}</p>
@if($entry->status === 'waiting')<p>{{ $automatic ? 'Ha találunk megfelelő szabad időpontot, értesítünk. A foglalást az ajánlaton külön kell megerősítened.' : 'A szalon a megadott elérhetőségeden egyeztet veled.' }}</p><form method="post" action="{{ route('waitlist.cancel', $token) }}">@csrf<button class="button secondary">Már nem kérek értesítést, visszavonom</button></form>@else<p>{{ $entry->status === 'booked' ? 'A várólistáról időpontot foglaltál.' : 'Ez a kérés már nem aktív.' }}</p>@endif
</section><a class="button secondary" href="{{ route('booking.form') }}">Másik időpontot keresek</a></div>
@endsection
