@extends('layouts.public')
@section('title','Sikeres foglalás')
@section('content')
<section class="wrap narrow success-page">
    <span class="success-symbol" aria-hidden="true"><x-icon name="check" /></span>
    <p class="eyebrow">MÁR A NAPTÁRUNKBAN VAGY</p>
    <h1>Várunk szeretettel!</h1>
    <p>A foglalásodat elmentettük.
        @if($confirmationChannel === 'email')
            Az e-mail-visszaigazolás küldése sorba került.
        @elseif($confirmationChannel === 'sms')
            Az SMS-visszaigazolás küldése sorba került.
        @else
            Automatikus visszaigazolás nem küldhető. Mentsd el a lenti kezelési linket, vagy keresd a szalont.
        @endif
    </p>
    <div class="panel">
        <h2>{{ $booking->service_name }}</h2>
        <p>{{ $booking->starts_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }} · {{ $booking->duration_minutes }} perc</p>
        <p>{{ $booking->staff->name }} · {{ $salon->address }}</p>
        <strong>@include('components.price', ['amount'=>$booking->price, 'kind'=>$booking->price_kind]){{ $booking->price_kind==='indicative' ? ' · tájékoztató ár':'' }}</strong>
    </div>
    <div class="panel" data-booking-link-save>
        <h2>Mentsd el a foglalásod linkjét</h2>
        <p>Ezen a bizalmas linken helyezheted át vagy mondhatod le az időpontodat. Őrizd meg, és ne oszd meg másokkal.</p>
        <p><button type="button" class="button" data-copy-booking-link aria-describedby="booking-link-status" hidden>Kezelési link másolása</button></p>
        <p><a class="button secondary" href="{{ route('booking.manage', $booking->manage_token) }}" data-booking-management-link>Foglalásom kezelése</a></p>
        <p id="booking-link-status" class="muted" role="status" aria-live="polite">A kezelőoldalt megnyitva a böngésződben is elmentheted a linket.</p>
        <div data-booking-link-fallback hidden><label>Foglalásod bizalmas linkje<input type="text" value="{{ route('booking.manage', $booking->manage_token) }}" readonly autocomplete="off" spellcheck="false" data-booking-link-value></label></div>
    </div>
    @include('components.calendar-actions', ['calendarUrl' => route('booking.calendar', $booking->manage_token)])
    @include('components.series-summary')
    @if($salon->demo)
        <div class="panel"><p class="eyebrow">DEMÓ · MINTAFOGLALÁS</p><h2>Így látja a tulajdonos</h2><p>Nézd meg a napi áttekintést és a munkafolyamatokat egy olvasó demófiókkal.</p>@if($demoAccessAvailable)@include('components.demo-entry', ['label' => 'Nézd meg a tulajdonosi oldalt', 'secondary' => true])@else<a class="button secondary" href="{{ route('admin.today') }}">Nézd meg a tulajdonosi oldalt</a>@endif</div>
    @endif
    <a class="button" href="{{ $demoGuestHomeUrl }}">Vissza a szalon oldalára</a>
</section>
@endsection
