@extends('layouts.admin')
@section('title','Szolgáltatások')
@section('content')<div class="page-heading"><div><p class="eyebrow">AMIT A VENDÉGEID VÁLASZTHATNAK</p><h1>Szolgáltatások</h1><p class="muted">Az ár és időtartam változtatása a korábbi foglalásokat nem írja át.</p></div></div>
@foreach($services->concat([new \App\Models\Service]) as $service)<details class="panel" @if(!$service->exists) open
@endif
><summary>{{ $service->exists ? $service->name.' · '.$service->duration_minutes.' perc · '.number_format($service->price, 0, ',', ' ').' Ft · '.($service->active ? 'Aktív':'Archivált'):'+ Új szolgáltatás' }}</summary><form method="post" action="{{ route('admin.services.save',$service->exists ? $service:null) }}">@csrf<div class="form-grid"><label>Megnevezés<input name="name" value="{{ $service->name }}" required maxlength="120"></label><label>Kategória<input name="category" value="{{ $service->category ?? 'Szolgáltatások' }}" required maxlength="80"></label></div><label>Rövid leírás<textarea name="description" rows="2">{{ $service->description }}</textarea></label><div class="form-grid three"><label>Időtartam (perc)<input name="duration_minutes" type="number" min="5" max="720" value="{{ $service->duration_minutes ?? 30 }}" required></label><label>Ár (Ft)<input name="price" type="number" min="0" value="{{ $service->price ?? 0 }}" required></label><label>Ár jellege<select name="price_kind">@foreach(['fixed'=>'Fix ár','from'=>'Induló ár','indicative'=>'Tájékoztató ár'] as $key=>$label)<option value="{{ $key }}" @selected($service->price_kind===$key)>{{ $label }}</option>
@endforeach
</select></label></div><div class="form-grid"><label>Előkészület (perc)<input name="buffer_before" type="number" min="0" max="180" value="{{ $service->buffer_before ?? 0 }}" required></label><label>Utómunka (perc)<input name="buffer_after" type="number" min="0" max="180" value="{{ $service->buffer_after ?? 0 }}" required></label></div><p class="muted">Ennyi időt tartunk szabadon a szolgáltatás előtt az előkészületre, illetve utána az utómunkára.</p><fieldset><legend>Alkalmas szakemberek</legend>@foreach($staff as $person)<label class="check"><input type="checkbox" name="staff[]" value="{{ $person->id }}" @checked($service->exists && $service->staff->contains($person->id))><span>{{ $person->name }}</span></label>
@endforeach
</fieldset><label class="check"><input type="checkbox" name="active" value="1" @checked(!$service->exists || $service->active)><span>Aktív, online foglalható szolgáltatás</span></label><button class="button">Szolgáltatás mentése</button></form>
@include('components.catalog-actions', ['item' => $service, 'type' => 'services'])
</details>
@endforeach


@endsection
