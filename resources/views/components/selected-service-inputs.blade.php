<div data-selected-service-inputs>
    @foreach(array_slice(app(\App\Services\ServiceSelection::class)->items($service), 1) as $item)<input type="hidden" name="extra_service_ids[]" value="{{ $item['id'] }}">@endforeach
</div>
