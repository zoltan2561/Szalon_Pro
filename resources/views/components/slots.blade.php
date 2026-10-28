@php
    $groupAnyStaff = $groupAnyStaff ?? false;
    $selectedSlot = old('slot', $selectedSlot ?? null);
    $displaySlots = collect($slots);
    if ($groupAnyStaff) {
        $displaySlots = $displaySlots->groupBy('starts_at')->map(function ($group) use ($selectedSlot) {
            return $group->first(fn (array $slot): bool => $selectedSlot === $slot['staff_id'].'|'.$slot['starts_at']) ?? $group->first();
        })->values();
    }
    $ambiguousSlots = $displaySlots
        ->groupBy('label')
        ->filter(fn ($group): bool => $group->pluck('starts_at')->unique()->count() > 1)
        ->keys();
@endphp
<fieldset class="slots-field"><legend>Szabad időpontok</legend><div class="slots">
    @forelse($displaySlots as $slot)
        <label class="slot"><input type="radio" name="slot" value="{{ $slot['staff_id'] }}|{{ $slot['starts_at'] }}" required @checked($selectedSlot === $slot['staff_id'].'|'.$slot['starts_at'])><span><strong>{{ $slot['label'] }}</strong><small>{{ $groupAnyStaff ? 'Elérhető szakember' : $slot['staff_name'] }}@if($ambiguousSlots->contains($slot['label'])) · UTC{{ $slot['offset'] }}@endif</small></span></label>
    @empty
        <p class="empty">Erre a napra nincs szabad időpont. Válassz másik napot.</p>
    @endforelse
</div></fieldset>
