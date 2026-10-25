@extends('layouts.admin')
@section('title', 'Értesítések')
@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">VISSZAIGAZOLÁSOK ÉS EMLÉKEZTETŐK</p>
        <h1>Értesítések</h1>
        <p class="muted">A foglalások akkor is érvényesek maradnak, ha egy értesítés nem jut el a vendéghez.</p>
    </div>
</div>

@if(config('mail.default') === 'array')
<div class="notice">Helyi levélpróba aktív. A rendszer nem küld valódi e-mailt.</div>
@endif

<section class="panel"><h2>Időzített feldolgozás</h2>@foreach(['email' => 'E-mail', 'sms' => 'SMS'] as $channel => $label)<p><strong>{{ $label }}</strong> · Utolsó futás: {{ $lastRuns[$channel]?->setTimezone('Europe/Budapest')->format('Y.m.d. H:i') ?? 'még nem ismert' }} (Budapest) · Esedékes sor: {{ $dueCounts[$channel] }}</p>@endforeach<p class="muted">Az utolsó futás nem kézbesítési igazolás; hibánál ellenőrizd a küldési naplót.</p></section>
@if($salon->featureEnabled('sms') && !$smsConfigured)
<div class="notice">Az SMS-modul be van kapcsolva, de nincs beállított szolgáltató. SMS nem kerül kiküldésre.</div>
@endif

<section class="panel">
    <h2>E-mailek</h2>
    @forelse($messages as $message)
    <div class="history-row">
        <div>
            <a href="{{ route('admin.booking', $message->booking_id) }}">Foglalás #{{ $message->booking_id }}</a>
            <p>{{ ['confirmation' => 'Visszaigazolás', 'changed' => 'Módosítás', 'cancelled' => 'Lemondás', 'reminder' => 'Emlékeztető'][$message->kind] ?? $message->kind }} · {{ $message->scheduled_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }}</p>
            <small>{{ $message->attempts }} próbálkozás @if($message->error_code)· {{ $message->error_code }}@endif</small>
        </div>
        <span class="badge">{{ ['pending' => 'Sorban áll', 'sending' => 'Küldés folyamatban', 'retry' => 'Újrapróbálkozásra vár', 'sent' => config('mail.default') === 'array' ? 'Helyi próba feldolgozva' : 'Küldő átvette', 'failed' => 'Sikertelen', 'obsolete' => 'Elavult, nem küldhető'][$message->status] ?? $message->status }}</span>
        @if(in_array($message->status, ['retry', 'failed'], true))
        <form method="post" action="{{ route('admin.retry', $message) }}">@csrf<button class="button secondary small">Újrapróbálkozás</button></form>
        @endif
    </div>
    @empty
    <div class="empty">Még nincs e-mail értesítés.</div>
    @endforelse
</section>
{{ $messages->links() }}

<section class="panel">
    <h2>SMS-emlékeztetők</h2>
    <p class="muted">Az SMS csak bekapcsolt modul és beállított szolgáltató esetén kerül sorba. A „szolgáltató átvette” állapot nem kézbesítési igazolás.</p>
    @forelse($smsMessages as $message)
    <div class="history-row">
        <div>
            <a href="{{ route('admin.booking', $message->booking_id) }}">Foglalás #{{ $message->booking_id }}</a>
            <p>{{ ['confirmation'=>'Visszaigazolás','changed'=>'Módosítás','cancelled'=>'Lemondás','reminder'=>'Emlékeztető','review'=>'Értékeléskérés'][$message->kind] ?? $message->kind }} · {{ $message->scheduled_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }}</p>
            <small>{{ $message->attempts }} próbálkozás @if($message->error_code)· {{ $message->error_code }}@endif</small>
        </div>
        <span class="badge">{{ ['pending' => 'Sorban áll', 'sending' => 'Küldés folyamatban', 'retry' => 'Újrapróbálkozásra vár', 'sent' => 'Szolgáltató átvette', 'failed' => 'Sikertelen', 'uncertain' => 'Bizonytalan — ellenőrzés szükséges', 'obsolete' => 'Elavult, nem küldhető'][$message->status] ?? $message->status }}</span>
        @if(in_array($message->status, ['retry', 'failed'], true) && $salon->featureEnabled('sms') && $smsConfigured)
        <form method="post" action="{{ route('admin.sms.retry', $message) }}">@csrf<button class="button secondary small">Újrapróbálkozás</button></form>
        @endif
    </div>
    @empty
    <div class="empty">Még nincs SMS-emlékeztető.</div>
    @endforelse
</section>
{{ $smsMessages->links() }}
@endsection
