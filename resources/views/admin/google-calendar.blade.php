@extends('layouts.admin')
@section('title','Google Naptár')
@section('content')
<div class="page-heading"><div><p class="eyebrow">SZAKEMBERENKÉNTI NAPTÁRKAPCSOLAT</p><h1>Google Naptár</h1><p class="muted">A SzalonPRO-foglalások a kapcsolt szakember elsődleges Google Naptárába kerülnek. A többi Google-esemény csak foglalt időként számít.</p></div></div>

@unless($configured)<div class="notice error">A Google-kapcsolat még nincs beállítva. Kérd az üzemeltetőtől a bekapcsolását.</div>@endunless
<div class="notice">A naptár ötpercenként frissül. Hibás vagy 15 percnél régebbi kapcsolatnál az érintett szakemberhez új időpont nem foglalható.</div>
<section class="panel"><h2>Kapcsolódás három lépésben</h2><ol class="setup-list"><li>Válaszd ki alább a munkatársat, és kattints a kapcsolásra.</li><li>A Google oldalán jelentkezz be, és engedélyezd a naptár használatát.</li><li>Visszatérés után ellenőrizd, hogy a kapcsolat és az első beolvasás sikeres-e.</li></ol><p class="muted">A Google-jelszavadat csak a Google bejelentkezési oldalán add meg.</p></section>

@forelse($staff as $person)
@php($connection=$connections->get($person->id))
<section class="panel"><div class="section-heading"><div><p class="eyebrow">{{ $person->title ?: 'SZAKEMBER' }}</p><h2>{{ $person->name }}</h2></div><span class="badge {{ $connection?->sync_status === 'connected' ? 'completed' : 'cancelled' }}">{{ ['connected'=>'Kapcsolódva','pending'=>'Első szinkronra vár','error'=>'Szinkronhiba','reconnect'=>'Új engedélyezés kell'][$connection?->sync_status] ?? 'Nincs kapcsolat' }}</span></div>
@if($connection)
<p>Kapcsolt naptár: <strong>{{ $connection->calendar_id }}</strong> · Beolvasott foglalt események: <strong>{{ $connection->busy_events_count }}</strong> · Utolsó sikeres beolvasás: <strong>{{ $connection->last_imported_at?->setTimezone($salon->timezone)->format('Y. m. d. H:i') ?? 'még nem történt' }}</strong> · Utolsó foglaláskivitel: <strong>{{ $connection->last_exported_at?->setTimezone($salon->timezone)->format('Y. m. d. H:i') ?? 'még nem történt' }}</strong>.</p>
@if($connection->last_error_code)<p class="notice error">Hibakód: {{ $connection->last_error_code }}. A naptárat frissítsd, vagy szükség esetén csatlakoztasd újra.</p>@endif
<div class="actions"><form method="post" action="{{ route('admin.google.sync',$connection) }}">@csrf<button class="button secondary">Frissítés most</button></form>@if($person->active)<form method="post" action="{{ route('admin.google.connect',$person) }}">@csrf<button class="button secondary" @disabled(!$configured)>Újraengedélyezés</button></form>@endif</div>
<details><summary>Kapcsolat bontása</summary><p>A kapcsolat és a helyben tárolt Google-foglaltság törlődik. A rendszer előbb megpróbálja törölni a saját kivitt eseményeit a Google Naptárból. Ha a Google-hozzáférés már nem működik, a második jelölővel a korábbi eseményeket ott hagyva is bonthatod a helyi kapcsolatot.</p><form method="post" action="{{ route('admin.google.disconnect',$connection) }}">@csrf<label class="check"><input type="checkbox" name="confirm" value="1" required><span>Kérem a kapcsolat bontását.</span></label><label class="check"><input type="checkbox" name="leave_events" value="1"><span>A Google-eseményeket ott hagyom, és külön kezelem őket.</span></label><button class="button danger">Kapcsolat bontása</button></form></details>
@else
<p class="muted">Az elsődleges Google Naptár kapcsolható. Válaszd ki a szakember Google-fiókját a Google bejelentkezési oldalon.</p>@if($person->active)<form method="post" action="{{ route('admin.google.connect',$person) }}">@csrf<button class="button" @disabled(!$configured)>Google Naptár kapcsolása</button></form>@else<p>A szakember archivált; új kapcsolat nem indítható.</p>@endif
@endif
</section>
@empty
<section class="panel"><p>Előbb vegyél fel aktív szakembert a Munkatársak oldalon.</p><a href="{{ route('admin.staff') }}">Munkatársak →</a></section>
@endforelse

<section class="panel"><h2>Hogyan működik?</h2><p>A kapcsolatot a szakember a saját fiókjával vagy a tulajdonos indíthatja, majd a megfelelő szakember Google-fiókjában engedélyezi. Foglaláskor és áthelyezéskor a SzalonPRO az irányadó; a Google-ben átírt saját foglalási eseményt a következő frissítés visszaállítja. A Google-esemény a foglalás előtti és utáni puffert is lefedi; a vendég tényleges időpontja az esemény leírásában olvasható. Sem a kivitt események, sem a beolvasott foglaltság nem tartalmaznak vendégnevet vagy elérhetőséget.</p><p>A szinkron ötpercenként fut, ha a szerveren működik az időzítő. Egy friss, öt percen belüli külső Google-módosítás még ütközhet; sürgős változás után használd a Frissítés gombot.</p></section>
@endsection
