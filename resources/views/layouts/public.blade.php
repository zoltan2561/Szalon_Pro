<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="{{ $salon->tagline }}"><meta name="referrer" content="no-referrer">
    <title>@yield('title', $salon->name) · {{ $salon->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/branding/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('salon.css') }}"><link rel="stylesheet" href="{{ asset('premium.css') }}">
</head>
<body class="public premium {{ $salon->theme }} font-{{ $salon->font }} layout-{{ $brand['layout'] }} {{ request()->routeIs('home') ? 'public-home' : 'public-inner' }}"
      style="--accent:{{ $brand['accent'] }};--on-accent:{{ $brand['on_accent'] }};--paper:{{ $brand['paper'] }};--ink:{{ $brand['ink'] }};--muted:{{ $brand['muted'] }};--soft:{{ $brand['surface'] }};--line:{{ $brand['line'] }};--radius:{{ $brand['radius'] }}px">
<a class="skip" href="#main">Ugrás a tartalomra</a>
@if($salon->demo)<div class="demo-strip">SzalonPRO bemutató <span>· Mintaadatok, AI-generált hangulatképek</span></div>@endif
<header class="public-header">
    <div class="header-inner wrap">
        <a class="brand" href="{{ route('home') }}">
            <img class="brand-logo {{ $brand['custom_logo'] ? 'custom-logo' : 'default-logo' }}" src="{{ $brand['logo'] }}" alt="" width="44" height="44">
            <span>{{ $salon->name }}</span>
        </a>
        <nav class="desktop-nav" aria-label="Főmenü">
            <a href="{{ route('home') }}#szolgaltatasok">Szolgáltatások</a><a href="{{ route('home') }}#rolunk">Rólunk</a><a href="{{ route('home') }}#kapcsolat">Kapcsolat</a>
            <a class="button" href="{{ route('booking.form') }}">{{ $brand['cta_label'] }} <x-icon name="arrow-right" /></a>
        </nav>
        <details class="mobile-menu"><summary aria-label="Menü"><x-icon name="list" /><span>Menü</span></summary>
            <nav aria-label="Mobil főmenü"><a href="{{ route('home') }}#szolgaltatasok">Szolgáltatások</a><a href="{{ route('home') }}#rolunk">Rólunk</a><a href="{{ route('home') }}#kapcsolat">Kapcsolat</a><a href="{{ route('booking.form') }}">Időpontfoglalás</a></nav>
        </details>
    </div>
</header>
<main id="main">@include('components.flash')@yield('content')</main>
<footer class="public-footer wrap">
    <div><a class="brand footer-brand" href="{{ route('home') }}"><img class="brand-logo {{ $brand['custom_logo'] ? 'custom-logo' : 'default-logo' }}" src="{{ $brand['logo'] }}" alt="" width="40" height="40"><span>{{ $salon->name }}</span></a><p>{{ $salon->tagline }}</p></div>
    <div><a href="{{ route('privacy') }}">Adatkezelés</a><a href="{{ route('login') }}">Munkatársi belépés</a><span class="muted">SzalonPRO</span></div>
</footer>
@unless(request()->routeIs('booking.*', 'login', 'privacy'))
<div class="mobile-booking-bar"><span>Egy kis idő magadra.</span><a class="button" href="{{ route('booking.form') }}">{{ $brand['cta_label'] }} <x-icon name="arrow-right" /></a></div>
@endunless
<script src="{{ asset('salon.js') }}" defer></script>
</body></html>
