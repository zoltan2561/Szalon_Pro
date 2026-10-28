@php
    $ambiguousSlots = collect($slots)
        ->groupBy(fn (array $slot): string => $slot['staff_id'].'|'.$slot['label'])
        ->filter(fn ($group): bool => $group->count() > 1)
        ->keys();
@endphp
<fieldset class="slots-field"><legend>Szabad időpontok</legend><div class="slots">
    @forelse($slots as $slot)
        <label class="slot"><input type="radio" name="slot" value="{{ $slot['staff_id'] }}|{{ $slot['starts_at'] }}" required @checked(old('slot') === $slot['staff_id'].'|'.$slot['starts_at'])><span><strong>{{ $slot['label'] }}</strong><small>{{ $slot['staff_name'] }}@if($ambiguousSlots->contains($slot['staff_id'].'|'.$slot['label'])) · UTC{{ $slot['offset'] }}@endif</small></span></label>
    @empty
        <p class="empty">Erre a napra nincs szabad időpont. Válassz másik napot.</p>
    @endforelse
</div></fieldset>
