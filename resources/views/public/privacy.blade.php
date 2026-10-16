@extends('layouts.public')
@section('title','Adatkezelés')
@section('content')<div class="wrap narrow"><p class="eyebrow">ADATKEZELÉS</p><h1 class="page-title">Adatkezelési tájékoztató</h1><div class="panel preserve">{{ $salon->privacy }}</div><p>Adatokkal kapcsolatos kérések: <a href="mailto:{{ $salon->email }}">{{ $salon->email }}</a></p></div>
@endsection
