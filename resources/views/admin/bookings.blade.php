@extends('layouts.admin')
@section('title', 'Foglalások')
@section('content')
@php($bookingDays = $bookings->getCollection()->groupBy(fn ($booking) => $booking->starts_at->setTimezone($salon->timezone)->toDateString()))
<div class="page-heading"><div><p class="eyebrow">A SZALON IDŐPONTJAI</p><h1>Foglalások</h1><p class="muted">Keresés vendég, telefonszám, nap vagy állapot alapján.</p></div>
@if(auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)<a class="button" href="{{ route('admin.create') }}">+ Új foglalás</a>@endif</div>
<nav class="booking-scope-tabs" aria-label="Foglalások időszaka">@foreach(['upcoming' => 'Közelgő', 'history' => 'Előzmények', 'all' => 'Összes'] as $key => $label)<a href="{{ route('admin.bookings.index', array_filter(['scope' => $key, 'q' => $search, 'date' => $date, 'status' => $status])) }}" @if($scope === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
<details class="panel booking-filter-panel" data-mobile-disclosure open><summary><span>Keresés és szűrők</span><small>Név, nap, állapot</small><x-icon name="arrow-right" /></summary>
<form data-auto-filter="bookings" method="get" action="{{ route('admin.bookings.index') }}">
    <input type="hidden" name="scope" value="{{ $scope }}">
    <div class="form-grid"><label>Vendég neve vagy telefonszáma<input name="q" type="search" inputmode="search" value="{{ $search }}" maxlength="120"></label>
    <label>Nap<input type="date" name="date" value="{{ $date }}"></label></div>
    <label>Állapot<select name="status"><option value="">Minden állapot</option>@foreach(['confirmed' => 'Visszaigazolt', 'completed' => 'Elvégezve', 'cancelled' => 'Lemondva', 'no_show' => 'Nem jelent meg'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label>
    <div class="actions"><button class="button">Keresés</button><a class="button secondary" href="{{ route('admin.bookings.index') }}">Szűrők törlése</a></div>
</form></details>
<section class="panel booking-results" data-auto-filter-results><h2>Találatok · {{ $bookings->total() }}</h2>
    @forelse($bookingDays as $dayKey => $dayBookings)
        @php($day = \Carbon\CarbonImmutable::parse($dayKey, $salon->timezone))
        <h3 class="booking-day-heading">{{ $day->format('Y. m. d.') }} · {{ $day->locale('hu')->isoFormat('dddd') }}</h3>
        <ul class="booking-result-list">
        @foreach($dayBookings as $booking)
            <li><a class="booking-result {{ $booking->status }}" href="{{ route('admin.booking', $booking) }}"><span class="booking-result-time"><strong>{{ $booking->starts_at->setTimezone($salon->timezone)->format('H:i') }}</strong><small>{{ $booking->ends_at->setTimezone($salon->timezone)->format('H:i') }}</small></span><span class="booking-result-main"><strong>{{ $booking->guest->name }}</strong><small>{{ $booking->service_name }} · {{ $booking->staff->name }}</small></span><small class="booking-result-phone">{{ $booking->guest->phone ?: 'Nincs telefonszám' }}</small><span class="badge {{ $booking->status }}">{{ $booking->visitLabel() }}</span></a></li>
        @endforeach
        </ul>
    @empty
        <p class="muted">Nincs a keresésnek megfelelő foglalás.</p>
    @endforelse
    {{ $bookings->links() }}
</section>
@endsection
