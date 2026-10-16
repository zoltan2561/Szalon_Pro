<!DOCTYPE html>
<html lang="hu">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>@yield('title','Mai nap') · SzalonPRO</title><link rel="icon" href="{{ asset('assets/branding/favicon.png') }}"><link rel="stylesheet" href="{{ asset('salon.css') }}"><link rel="stylesheet" href="{{ asset('premium.css') }}"></head>
<body class="admin premium-admin">
<a class="skip" href="#main">Ugrás a tartalomra</a>
<aside class="sidebar">
<a class="brand" href="{{ route('admin.today') }}"><span class="brand-mark"><img src="{{ asset('assets/branding/sp-mark.png') }}" alt="" width="31" height="31"></span><span>Szalon<span class="muted">PRO</span></span></a>
<p class="workspace-label">{{ $salon->name }}</p>
<nav aria-label="Munkafelület">
@foreach(['today'=>['clock','Mai nap'],'calendar'=>['calendar-blank','Naptár'],'guests'=>['users','Vendégeim']] as $key=>$item)
<a class="{{ request()->routeIs('admin.'.$key) ? 'active':'' }}" href="{{ route('admin.'.$key) }}"><x-icon :name="$item[0]" />{{ $item[1] }}</a>
@endforeach
@if(auth()->user()->role==='owner')
@foreach(['services'=>['scissors','Szolgáltatások'],'staff'=>['user','Munkatársak'],'notifications'=>['envelope-simple','Értesítések'],'settings'=>['gear-six','Beállítások']] as $key=>$item)
<a class="{{ request()->routeIs('admin.'.$key) ? 'active':'' }}" href="{{ route('admin.'.$key) }}"><x-icon :name="$item[0]" />{{ $item[1] }}</a>
@endforeach
@endif
</nav>
<div class="sidebar-bottom">
<a href="{{ route('home') }}" target="_blank" rel="noopener">Weboldal előnézete ↗</a>
<p>{{ auth()->user()->name }}<small>{{ auth()->user()->role==='owner' ? 'Tulajdonos':'Munkatárs' }}</small></p>
<form method="post" action="{{ route('logout') }}">@csrf<button class="text-button">Kijelentkezés</button></form>
</div>
</aside>
<div class="admin-shell">
<header class="admin-top">
<span class="eyebrow">A SZALONOD, ÁTLÁTHATÓAN</span>
<div><span class="live-dot" aria-hidden="true"></span>{{ $salon->demo ? 'Bemutató környezet':'Munkafelület' }}</div>
<form class="mobile-logout" method="post" action="{{ route('logout') }}">@csrf<button class="text-button">Kilépés</button></form>
</header>
<main id="main" class="admin-content">@include('components.flash')@yield('content')</main>
<footer class="admin-footer">SzalonPRO · {{ config('salon.version') }} <span>Kevesebb szervezés. Több figyelem.</span></footer>
</div>
<script src="{{ asset('salon.js') }}" defer></script>
</body></html>
