@extends('errors.layout')
@section('code', '422')
@section('title', 'Ezt a kérést nem tudtuk értelmezni')
@section('message', 'A hivatkozásban szereplő adat, például a dátum hibás vagy elavult. Nyisd meg újra az oldalt a menüből, és válaszd ki a kívánt napot.')
@section('action')
@if(request()->is('admin*') && auth()->check())<p><a class="button" href="{{ route('admin.calendar') }}">A mai napi naptár megnyitása</a></p>@endif
@endsection
