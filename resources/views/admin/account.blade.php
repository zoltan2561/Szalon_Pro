@extends('layouts.admin')
@section('title', 'Fiók')
@section('content')
<div class="page-heading"><div><p class="eyebrow">SZEMÉLYES HOZZÁFÉRÉS</p><h1>Fiók</h1><p class="muted">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p></div></div>
<section class="panel"><h2>Jelszó módosítása</h2><p>Válassz legalább 12 karakteres jelszót. A módosítás után minden eszközön újra be kell jelentkezned.</p>
<form method="post" action="{{ route('admin.account.password') }}">@csrf
<label>Jelenlegi jelszó<input type="password" name="current_password" autocomplete="current-password" required></label>
<label>Új jelszó<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="200" required></label>
<label>Új jelszó ismét<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="200" required></label>
<button class="button">Jelszó módosítása</button></form></section>
@endsection
