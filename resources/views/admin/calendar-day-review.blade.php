@if($isTodayPage && ($reviewBookings ?? collect())->isNotEmpty())
<section class="panel day-review"><p class="eyebrow">A NAP VÉGÉN</p><h2>Napi ellenőrzés · {{ $reviewBookings->count() }}</h2><p class="muted">Ezek az alkalmak idő alapján automatikusan lezárultak. Ellenőrizd, hogy a vendég valóban megjelent-e, és erősítsd meg az elvégzést.</p>
@foreach($reviewBookings as $visit)<div class="history-row"><div><strong>{{ $visit->starts_at->setTimezone($salon->timezone)->format('H:i') }} · {{ $visit->guest->name }}</strong><p>{{ $visit->service_name }} · {{ $visit->staff->name }}</p></div><a class="button secondary" href="{{ route('admin.booking', $visit) }}">Ellenőrzöm</a></div>@endforeach
</section>
@endif
