@extends('layouts.admin')
@section('title', 'Foglalások')
@section('content')
<div class="page-heading"><div><p class="eyebrow">A SZALON IDŐPONTJAI</p><h1>Foglalások</h1><p class="muted">Keresés vendég, telefonszám, nap vagy állapot alapján.</p></div>
@if(auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings)<a class="button" href="{{ route('admin.create') }}">+ Új foglalás</a>@endif</div>
<section class="panel"><form method="get" action="{{ route('admin.bookings.index') }}">
    <div class="form-grid"><label>Vendég neve vagy telefonszáma<input name="q" type="search" inputmode="search" value="{{ $search }}" maxlength="120"></label>
    <label>Nap<input type="date" name="date" value="{{ $date }}"></label></div>
    <label>Állapot<select name="status"><option value="">Minden állapot</option>@foreach(['confirmed' => 'Visszaigazolt', 'completed' => 'Elvégezve', 'cancelled' => 'Lemondva', 'no_show' => 'Nem jelent meg'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label>
    <div class="actions"><button class="button">Keresés</button><a class="button secondary" href="{{ route('admin.bookings.index') }}">Szűrők törlése</a></div>
</form></section>
<section class="panel"><h2>Találatok · {{ $bookings->total() }}</h2>
    @forelse($bookings as $booking)
        <div class="history-row"><div><strong><a href="{{ route('admin.booking', $booking) }}">{{ $booking->guest->name }}</a></strong><p>{{ $booking->starts_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }} · {{ $booking->service_name }} · {{ $booking->staff->name }}</p><p>{{ $booking->guest->phone ?: 'Nincs telefonszám' }} · {{ $booking->label() }}</p></div><a href="{{ route('admin.booking', $booking) }}">Foglalás megnyitása ↗</a></div>
    @empty
        <p class="muted">Nincs a keresésnek megfelelő foglalás.</p>
    @endforelse
    {{ $bookings->links() }}
</section>
@endsection
