@extends('errors.layout')
@section('code', '405')
@section('title', 'Ez a művelet itt nem használható')
@section('message', 'A megnyitott hivatkozás erre a módra nem érvényes. Térj vissza a kezdőlapra, és onnan indítsd újra a műveletet.')
@section('action')
@if(request()->is('admin*') && auth()->check())<p><a class="button" href="{{ route('admin.today') }}">Vissza a Mai nap nézetre</a></p>@endif
@endsection
