@extends('errors.layout')
@section('code', '429')
@section('title', 'Túl sok kérés érkezett')
@php($retryAfter = max(1, (int) (($exception ?? null)?->getHeaders()['Retry-After'] ?? 60)))
@section('message', 'Próbáld újra '.$retryAfter.' másodperc múlva.')
@section('action')
<p><a href="{{ request()->is('elfelejtett-jelszo', 'uj-jelszo*') ? route('password.request') : route('booking.form') }}">{{ request()->is('elfelejtett-jelszo', 'uj-jelszo*') ? 'Vissza a jelszókérő űrlapra' : 'Vissza a foglalási űrlapra' }}</a></p>
@endsection
