<!DOCTYPE html>
<html lang="hu">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="{{ $salon->tagline }}"><meta name="referrer" content="no-referrer"><title>@yield('title', $salon->name) · {{ $salon->name }}</title><link rel="stylesheet" href="{{ asset('salon.css') }}"></head>
<body class="public {{ $salon->theme }} font-{{ $salon->font }}" style="--accent:{{ $salon->accent }}">
<a class="skip" href="#main">Ugrás a tartalomra</a>
@if($salon->demo)<div class="demo-strip">SzalonPRO bemutató · Minden név, szolgáltatás és foglalás tesztadat.</div>
@endif

<header class="public-header wrap"><a class="brand" href="{{ route('home') }}">@if($salon->logo)<img src="{{ Storage::url($salon->logo) }}" alt="{{ $salon->name }}" width="44" height="44">
@else
<span class="brand-mark" aria-hidden="true">{{ mb_substr($salon->name,0,1) }}</span>
@endif
<span>{{ $salon->name }}</span></a><nav aria-label="Főmenü"><a href="{{ route('home') }}#szolgaltatasok">Szolgáltatások</a><a href="{{ route('home') }}#kapcsolat">Kapcsolat</a><a class="button small" href="{{ route('booking.form') }}">Időpontot foglalok <span aria-hidden="true">↗</span></a></nav></header>
<main id="main">@include('components.flash')@yield('content')</main>
<footer class="public-footer wrap"><div><strong>{{ $salon->name }}</strong><p>{{ $salon->tagline }}</p></div><div><a href="{{ route('privacy') }}">Adatkezelési tájékoztató</a><a href="{{ route('login') }}">Munkatársi belépés</a><span class="muted">SzalonPRO</span></div></footer>
<script src="{{ asset('salon.js') }}" defer></script></body></html>
