@extends('errors.layout')
@section('code', '500')
@section('title', 'Átmeneti hiba történt')
@section('message', 'A kérést most nem tudtuk befejezni. Ha foglalást küldtél be, ellenőrizd a visszaigazolást vagy keresd a szalont, mielőtt újra próbálkozol.')
@section('action')
@if($errorPhone || $errorEmail)<p>@if($errorPhone)Telefon: <a href="tel:{{ preg_replace('/[^+0-9]/', '', $errorPhone) }}">{{ $errorPhone }}</a>@endif @if($errorEmail)<br>E-mail: <a href="mailto:{{ $errorEmail }}">{{ $errorEmail }}</a>@endif</p>@endif
@endsection
