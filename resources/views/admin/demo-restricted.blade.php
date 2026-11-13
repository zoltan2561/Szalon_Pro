@extends('layouts.admin')
@php($guestFlow = $guestFlow ?? false)
@section('title', $guestFlow ? 'Próbáld ki vendégként' : 'A saját szalonodban elérhető')
@section('content')
<section class="panel demo-restricted" aria-labelledby="demo-restricted-title">
    @if($guestFlow)
    <p class="eyebrow">VENDÉGOLDAL</p>
    <h1 id="demo-restricted-title">A foglalást vendégként próbáld ki.</h1>
    <p>{{ $description }} Lépj át vendégként, és a foglalás ott végigpróbálható.</p>
    <form method="post" action="{{ route('logout') }}">@csrf<input type="hidden" name="return_to" value="booking"><button class="button">Vendégként próbálom ki <x-icon name="arrow-right" /></button></form>
    <p><a href="{{ route('admin.today') }}">Vissza a Mai nap nézetre</a></p>
    @else
    <p class="eyebrow">OLVASÓ BEMUTATÓ</p>
    <h1 id="demo-restricted-title">Ez a rész a demóban csak bemutató.</h1>
    <p>{{ $description }} Saját szalonnál elérhető.</p>
    <a class="button" href="{{ route('admin.today') }}">Vissza a Mai nap nézetre <x-icon name="arrow-right" /></a>
    @endif
</section>
@endsection
