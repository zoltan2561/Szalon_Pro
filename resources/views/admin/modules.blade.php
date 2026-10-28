@extends('layouts.admin')
@section('title','Modulok')
@section('content')
<div class="page-heading"><div><p class="eyebrow">A SZALONODHOZ IGAZÍTVA</p><h1>Modulok</h1><p class="muted">Csak azokat a kiegészítőket használd, amelyek segítik a napi munkát. Kikapcsoláskor a már rögzített adatok megmaradnak.</p></div></div>

<form method="post" action="{{ route('admin.modules.update') }}">
@csrf
@foreach($moduleGroups as $group => $keys)
<section class="panel" aria-labelledby="module-group-{{ $loop->index }}">
<h2 id="module-group-{{ $loop->index }}">{{ $group }}</h2>
@foreach($keys as $key)
@php($module = $modules[$key])
<label class="check"><input type="checkbox" name="features[{{ $key }}]" value="1" @checked($salon->featureEnabled($key))><span><strong>{{ $module['title'] }}</strong><br>{{ $module['description'] }}</span></label>
@if($key === 'sms' && !$smsConfigured)<p class="notice">Az SMS-küldés még nincs szolgáltatóhoz kapcsolva; a kapcsoló önmagában nem indít küldést.</p>@endif
@endforeach
</section>
@endforeach
<button class="button">Modulok mentése</button>
</form>
@if($salon->featureEnabled('no_show_protection'))<form method="post" action="{{ route('admin.policy.no-show') }}" class="panel">@csrf<h2>Meg nem jelenés szabálya</h2><p>Az egyező e-mail vagy telefonszám alapján számolunk, a vendégkartonokat nem vonjuk össze. Közös családi elérhetőségnél is lehet egyezés; a kezelő telefonon mindig felvehet foglalást.</p><label>Korlátozás ennyi meg nem jelenés után (0 = nincs korlátozás)<input name="no_show_limit" type="number" min="0" max="100" value="{{ $salon->no_show_limit }}" required></label><label>Online lemondási határidő (óra)<input type="number" name="cancel_notice_hours" min="0" max="168" value="{{ $salon->cancel_notice_hours }}" required></label><label>Foglalási feltételek<textarea name="terms" required maxlength="5000">{{ $salon->terms }}</textarea></label><button class="button">Szabályok mentése</button></form>@endif
@if($salon->featureEnabled('phone_booking'))<form method="post" action="{{ route('admin.policy.contact') }}" class="panel">@csrf<h2>Kötelező vendégadat</h2><label>Elérhetőség<select name="contact_mode">@foreach(['email'=>'E-mail','phone'=>'Telefon','both'=>'Mindkettő'] as $key=>$label)<option value="{{ $key }}" @selected($salon->contact_mode === $key)>{{ $label }}</option>@endforeach</select></label><p>E-mail nélküli foglalásnál a kezelési link SMS-küldéséhez az SMS-modul és a szolgáltató beállítása is szükséges.</p><button class="button">Elérhetőség mentése</button></form>@endif
@if($salon->featureEnabled('reviews'))<form method="post" action="{{ route('admin.policy.reviews') }}" class="panel">@csrf<h2>Értékeléskérés</h2><label>Google-értékelési link (HTTPS)<input type="url" name="review_url" value="{{ $salon->review_url }}" required></label><label>Kérés az elvégzés rögzítése után (óra)<input type="number" name="review_delay_hours" min="0" max="168" value="{{ $salon->review_delay_hours }}" required></label><p>Csak külön hozzájárulással, azonos elérhetőséghez legfeljebb 90 naponta. Nincs jutalomhoz vagy kedvezményhez kötött értékelés.</p><button class="button">Értékeléskérés mentése</button></form>@endif
@endsection
