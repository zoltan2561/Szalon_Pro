@extends('layouts.public')
@section('title','Munkatársi belépés')
@section('content')<section class="wrap login-wrap"><div class="panel"><p class="eyebrow">SZALONPRO MUNKAFELÜLET</p><h1 class="page-title">Jó újra itt.</h1><p class="muted">Lépj be, és nézd meg, ki érkezik ma.</p><form action="{{ route('login.submit') }}" method="post">@csrf<label>E-mail-cím<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label><label>Jelszó<input type="password" name="password" autocomplete="current-password" required></label><button class="button full">Belépés a szalonba ↗</button></form><p class="auth-return"><a href="{{ route('password.request') }}">Elfelejtetted a jelszavad?</a></p></div></section>
@endsection
