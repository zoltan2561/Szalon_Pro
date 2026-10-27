@extends('layouts.admin')
@section('title','Vendégeim')
@section('content')<div class="page-heading"><div><p class="eyebrow">ISMERŐS ARCOK, SZEMÉLYES FIGYELEM</p><h1>Vendégeim</h1></div><form class="inline-form"><label class="sr-only" for="guest-search">Vendég keresése</label><input id="guest-search" name="q" type="search" inputmode="search" data-live-guests value="{{ $search }}" placeholder="Név, e-mail vagy telefon"><button class="button secondary">Keresés</button></form></div><div class="guest-grid" data-guest-list>@forelse($guests as $guest)<a class="panel guest-card" href="{{ route('admin.guest',$guest) }}"><span class="avatar">{{ mb_substr($guest->name,0,1) }}</span><h2>{{ $guest->name }}</h2><p>{{ $guest->email ?: 'Nincs e-mail' }}<br>{{ $guest->phone }}</p><span class="eyebrow">VENDÉGKARTON ↗</span></a>
@empty
<div class="panel empty">Nincs találat. Az új vendég első foglalásával automatikusan létrejön a kartonja.</div>
@endforelse
</div>{{ $guests->withQueryString()->links() }}
@endsection
