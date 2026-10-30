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
    $groupPeriods = $displaySlots->count() > 12;
    $periods = $groupPeriods ? $displaySlots->groupBy(function (array $slot): string {
        $hour = (int) substr($slot['label'], 0, 2);
        return $hour < 12 ? 'Délelőtt' : ($hour < 17 ? 'Délután' : 'Este');
    }) : ($displaySlots->isNotEmpty() ? collect(['' => $displaySlots]) : collect());
@endphp
<fieldset class="slots-field"><legend>Szabad időpontok</legend>
@if($showSlotDate ?? false)
<p class="manual-slot-date" data-slot-date><span>Időpontválasztás erre a napra</span><time datetime="{{ $date }}">{{ \Carbon\CarbonImmutable::parse($date, $salon->timezone)->locale('hu')->isoFormat('YYYY. MMMM D., dddd') }}</time></p>
@endif
<div class="slots{{ $groupPeriods ? ' slots-grouped' : '' }}">
    @forelse($periods as $period => $periodSlots)
        @if($groupPeriods)<details class="slot-period" @if($loop->first || $periodSlots->contains(fn (array $slot): bool => $selectedSlot === $slot['staff_id'].'|'.$slot['starts_at'])) open @endif><summary>{{ $period }} <span>{{ $periodSlots->count() }} időpont</span></summary><div class="slot-options">@endif
        @foreach($periodSlots as $slot)
        <label class="slot"><input type="radio" name="slot" value="{{ $slot['staff_id'] }}|{{ $slot['starts_at'] }}" required @checked($selectedSlot === $slot['staff_id'].'|'.$slot['starts_at'])><span><strong>{{ $slot['label'] }}</strong><small>{{ $slot['staff_name'] }}@if($ambiguousSlots->contains($slot['label'])) · UTC{{ $slot['offset'] }}@endif</small></span></label>
        @endforeach
        @if($groupPeriods)</div></details>@endif
    @empty
        <div class="empty slot-recovery"><p>Erre a napra nincs szabad időpont. Válassz másik napot.</p>
        @if(!empty($nextAvailableUrl) && !empty($nextAvailable))
            <p><strong>Következő szabad: {{ \Carbon\CarbonImmutable::parse($nextAvailable['date'], $salon->timezone)->locale('hu')->isoFormat('MMMM D., dddd') }} · {{ $nextAvailable['slots'][0]['label'] }}</strong></p>
            <a class="button" href="{{ $nextAvailableUrl }}#booking-slots" data-next-available>Erre a napra váltok</a>
        @elseif(!empty($showAvailabilityRecovery))
            <p>Ehhez a választáshoz nincs későbbi foglalható nap. Próbálj másik napot vagy szakembert, vagy érdeklődj a szalonnál.</p>
        @endif
        @if($salon->featureEnabled('waitlist') && !$salon->demo && $salon->phone)
            <a href="tel:{{ preg_replace('/[^+0-9]/', '', $salon->phone) }}">Várólistát kérek a szalontól</a>
        @endif
        @if($salon->featureEnabled('waitlist') && !request()->is('admin/*'))<a class="button secondary" href="{{ route('waitlist.join', array_filter(['service_id' => $service?->id, 'staff_id' => $staffId ?? null, 'date' => $date ?? null])) }}">Szóljatok, ha lesz szabad hely</a>@endif
        </div>
    @endforelse
</div></fieldset>
