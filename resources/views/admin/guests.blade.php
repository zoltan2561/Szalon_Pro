@extends('layouts.admin')
@section('title', 'Vendégeim')
@section('content')
<div class="page-heading"><div><p class="eyebrow">ISMERŐS ARCOK, SZEMÉLYES FIGYELEM</p><h1>Vendégeim</h1><p class="muted">A látogatások száma és ideje az elvégzett foglalásokat mutatja.</p></div></div>
<form method="get" action="{{ route('admin.guests') }}" class="panel" data-live-guests-form>
    <div class="form-grid"><label>Vendég keresése<input name="q" type="search" inputmode="search" data-live-guests value="{{ $search }}" maxlength="120" placeholder="Név, e-mail vagy telefon"></label>
    <label>Rendezés<select name="sort" data-guest-sort><option value="name" @selected($sort === 'name')>Név szerint</option><option value="last_visit" @selected($sort === 'last_visit')>Legutóbbi látogatás szerint</option></select></label></div>
    <button class="button secondary">Keresés</button><p class="muted" data-guest-list-status aria-live="polite"></p>
</form>
<section class="panel guest-list-results" data-guest-list aria-label="Vendéglista">
    <h2>Vendégek · {{ $guests->total() }}</h2>
    @if($guests->isNotEmpty())
        <div class="guest-table-wrap"><table class="guest-table"><thead><tr><th scope="col">Vendég</th><th scope="col">Telefonszám</th><th scope="col">Legutóbbi látogatás</th><th scope="col">Látogatások</th></tr></thead><tbody>
        @foreach($guests as $guest)
            <tr><td data-label="Vendég"><a href="{{ route('admin.guest', $guest) }}">{{ $guest->name }}</a>@if($guest->email)<small>{{ $guest->email }}</small>@endif</td><td data-label="Telefonszám">{{ $guest->phone ?: 'Nincs megadva' }}</td><td data-label="Legutóbbi látogatás">{{ $guest->last_visit_at ? \Carbon\CarbonImmutable::parse($guest->last_visit_at, 'UTC')->setTimezone($salon->timezone)->format('Y. m. d. H:i') : 'Még nem volt' }}</td><td data-label="Látogatások">{{ $guest->visit_count }}</td></tr>
        @endforeach
        </tbody></table></div>
    @else
        <p class="muted">Nincs találat. Az új vendég első foglalásával automatikusan létrejön az adatlapja.</p>
    @endif
    {{ $guests->links() }}
</section>
@endsection
