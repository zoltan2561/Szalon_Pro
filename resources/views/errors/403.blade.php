@extends('errors.layout')
@section('code', '403')
@section('title', 'Ehhez nincs jogosultságod')
@section('message', 'Ez a rész nem érhető el ezzel a hozzáféréssel. Ha szükséged van rá, kérd a szalon tulajdonosát, hogy bővítse a jogosultságodat.')
@section('action')
@if(request()->is('admin*') && auth()->check())<p><a class="button" href="{{ route('admin.today') }}">Vissza a Mai nap nézetre</a></p>@endif
@endsection
