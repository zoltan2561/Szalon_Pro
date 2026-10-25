@extends('layouts.public')
@section('title','Értékeléskérő üzenetek')
@section('content')<main class="narrow wrap"><section class="panel"><h1>Értékeléskérő üzenetek</h1>@if($unsubscribed)<p>Leiratkoztál. A szalon nem küld több értékeléskérő üzenetet erre a vendégkartonra. A foglalásodról szóló szükséges értesítéseket ez nem érinti.</p>@else<p>Egy kattintással leiratkozhatsz az értékeléskérő üzenetekről.</p><form method="post" action="{{ request()->fullUrl() }}">@csrf<button class="button">Leiratkozom</button></form>@endif</section></main>@endsection
