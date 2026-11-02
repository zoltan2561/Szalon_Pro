@extends('layouts.public')
@section('title', $brand['content']['privacy_title'])
@section('content')<div class="wrap narrow"><p class="eyebrow">{{ $brand['content']['privacy_eyebrow'] }}</p><h1 class="page-title">{{ $brand['content']['privacy_heading'] }}</h1><div class="panel preserve">{{ $salon->privacy ?: 'A tájékoztató hamarosan elérhető.' }}</div>@if($salon->email)<p>{{ $brand['content']['privacy_contact'] }} <a href="mailto:{{ $salon->email }}">{{ $salon->email }}</a></p>@endif</div>
@endsection
