@extends('errors.layout')
@section('code', '429')
@section('title', 'Túl sok kérés érkezett')
@php
    $retryAfter = max(1, (int) (($exception ?? null)?->getHeaders()['Retry-After'] ?? 60));
    $waitingTime = $retryAfter > 60 ? (int) ceil($retryAfter / 60).' perc' : $retryAfter.' másodperc';
    [$returnRoute, $returnLabel] = match (true) {
        request()->is('belepes') => ['login', 'Vissza a belépéshez'],
        request()->is('elfelejtett-jelszo', 'uj-jelszo*') => ['password.request', 'Vissza a jelszókérő űrlapra'],
        default => ['booking.form', 'Vissza a foglalási űrlapra'],
    };
@endphp
@section('message', 'Próbáld újra '.$waitingTime.' múlva.')
@section('action')
<p><a href="{{ route($returnRoute) }}">{{ $returnLabel }}</a></p>
@endsection
