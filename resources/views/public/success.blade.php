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
    <p>A saját foglalásodat ezen a bizalmas linken kezelheted. Őrizd meg, és ne oszd meg másokkal.</p>
    <p><a class="button secondary" href="{{ route('booking.manage', $booking->manage_token) }}">Foglalásom kezelése</a></p>
    <a class="button" href="{{ route('home') }}">Vissza a szalon oldalára</a>
</section>
@endsection
