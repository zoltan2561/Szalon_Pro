<!doctype html>
<html lang="hu">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>@yield('title') · {{ $errorSalonName ?? 'SzalonPRO' }}</title><link rel="stylesheet" href="{{ asset('salon.css') }}"></head>
<body class="error-page"><main class="panel"><p class="eyebrow">@yield('code')</p><h1>@yield('title')</h1><p>@yield('message')</p>@yield('action')<p><a href="{{ route('home') }}">Vissza a kezdőlapra</a></p></main></body>
</html>
