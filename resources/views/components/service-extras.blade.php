@if($services->count() > 1)
<details class="service-extras" data-service-extras @if(request()->filled('extra_service_ids')) open @endif>
    <summary>További szolgáltatást is szeretnék</summary>
    <p class="muted">Az időt és az árat együtt számoljuk. Olyan szakembert mutatunk, aki mindegyik szolgáltatást elvégzi.</p>
    @foreach($services as $extra)
        @if($extra->id !== $service?->id)<label class="check"><input type="checkbox" name="extra_service_ids[]" value="{{ $extra->id }}" @checked(in_array($extra->id, request()->input('extra_service_ids', [])))><span>{{ $extra->name }}<small>{{ $extra->duration_minutes }} perc · @include('components.price', ['amount' => $extra->price, 'kind' => $extra->price_kind])</small></span></label>@endif
    @endforeach
    <button type="button" class="button secondary" data-extras-done>Kiválasztva, tovább</button>
    @if($service?->relationLoaded('selectedServices') && $service->staff->isEmpty())<p class="notice">Ehhez a kombinációhoz nincs közös szakember. Vegyél ki egy szolgáltatást, vagy kérj két külön alkalmat.</p>@endif
</details>
@endif
