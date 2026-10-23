@extends('layouts.admin')
@section('title','Modulok')
@section('content')
<div class="page-heading"><div><p class="eyebrow">A SZALONODHOZ IGAZÍTVA</p><h1>Modulok</h1><p class="muted">Csak azokat a kiegészítőket használd, amelyek segítik a napi munkát. Kikapcsoláskor a már rögzített adatok megmaradnak.</p></div></div>

<form method="post" action="{{ route('admin.modules.update') }}">
@csrf
@foreach($modules as $key => $module)
<section class="panel">
<label class="check"><input type="checkbox" name="features[{{ $key }}]" value="1" @checked($salon->featureEnabled($key))><span><strong>{{ $module['title'] }}</strong><br>{{ $module['description'] }}</span></label>
@if($key === 'sms' && !$smsConfigured)<p class="notice">Az SMS-küldés még nincs szolgáltatóhoz kapcsolva; a kapcsoló önmagában nem indít küldést.</p>@endif
</section>
@endforeach
<button class="button">Modulok mentése</button>
</form>
@endsection
