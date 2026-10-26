@extends('layouts.public')
@section('title', 'Elfelejtett jelszó')
@section('content')<section class="wrap login-wrap"><div class="panel"><p class="eyebrow">ÚJRA HOZZÁFÉRHETSZ</p><h1 class="page-title">Elfelejtetted a jelszavad?</h1><p>Add meg a munkatársi belépéshez használt e-mail-címedet. Ha van ilyen fiók, elküldjük az egyszer használható visszaállító hivatkozást.</p><form action="{{ route('password.email') }}" method="post">@csrf<label>E-mail-cím<input name="email" type="email" value="{{ old('email') }}" maxlength="200" autocomplete="username" required></label><button class="button full">Visszaállító hivatkozást kérek</button></form><p class="auth-return"><a href="{{ route('login') }}">Vissza a belépéshez</a></p></div></section>
@endsection
