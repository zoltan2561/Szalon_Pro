@extends('errors.layout')
@section('code', '419')
@section('title', 'A munkamenet lejárt')
@section('message', 'A biztonságod érdekében nyisd meg újra a foglalási oldalt, és ellenőrizd az adatokat. A beküldést nem ismételtük meg.')
@section('action')<p><a class="button" href="{{ route('booking.form') }}">Vissza az időpontfoglaláshoz</a></p>@endsection
