@extends('layouts.admin')
@section('title', 'A saját szalonodban elérhető')
@section('content')
<section class="panel demo-restricted" aria-labelledby="demo-restricted-title">
    <p class="eyebrow">OLVASÓ BEMUTATÓ</p>
    <h1 id="demo-restricted-title">Ez a rész a demóban csak bemutató.</h1>
    <p>{{ $description }} Saját szalonnál elérhető.</p>
    <a class="button" href="{{ route('admin.today') }}">Vissza a Mai nap nézetre <x-icon name="arrow-right" /></a>
</section>
@endsection
