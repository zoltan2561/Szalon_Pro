@extends('layouts.admin')
@section('title', 'Átszervezés áttekintése')
@section('content')
<div class="page-heading"><div><p class="eyebrow">MÉG NEM MENTETTÜNK</p><h1>{{ $plan['mode'] === 'swap' ? 'Két időpont cseréje' : 'A nap átszervezése' }}</h1><p class="muted">{{ count($plan['rows']) }} foglalást érint. Minden időpont a szalon helyi ideje szerint látható.</p></div></div>
@unless($plan['available'])<div class="notice error" role="alert">Van olyan időpont, amely nem helyezhető át. Egyik foglalást sem módosítottuk. Válassz másik eltolást vagy másik cserepartnert.</div>@endunless
<section class="panel reorganization-preview"><h2>Ezek változnának</h2>
@foreach($plan['rows'] as $row)<article class="reorganization-row"><h3>{{ $row['guest'] }}</h3><p class="muted">{{ $row['service'] }}</p><div class="booking-time-change"><div><small>Jelenleg</small><strong>{{ \Carbon\CarbonImmutable::parse($row['old_starts_at'])->setTimezone($salon->timezone)->format('Y. m. d. H:i') }}</strong><span>{{ $row['old_staff'] }}</span></div><div><small>Módosítás után</small><strong>{{ \Carbon\CarbonImmutable::parse($row['starts_at'])->setTimezone($salon->timezone)->format('Y. m. d. H:i') }} – {{ \Carbon\CarbonImmutable::parse($row['ends_at'])->setTimezone($salon->timezone)->format('H:i') }}</strong><span>{{ $row['staff'] }}</span></div></div>
@foreach($row['conflicts'] as $conflict)<p class="notice error">{{ $conflict }}</p>@endforeach</article>@endforeach
</section>
<div class="panel"><p>A mentéskor minden időpontot újra ellenőrzünk. Ütközés vagy közben érkezett módosítás esetén az eredeti foglalások maradnak érvényben.</p><p class="muted">Az előnézet 10 percig érvényes. A módosítások bekerülnek a foglalások előzményeibe; a beállított vendégértesítések sorba állnak.</p><div class="actions">
@if($planToken)<form method="post" action="{{ route('admin.booking.reorganize.commit', $booking) }}">@csrf<input type="hidden" name="plan_token" value="{{ $planToken }}"><button class="button" data-reorganization-save>{{ count($plan['rows']) }} foglalás módosításának mentése</button></form>@endif
<a class="button secondary" href="{{ route('admin.booking', $booking) }}#booking-reorganize">Vissza, másképp módosítom</a></div></div>
@endsection
