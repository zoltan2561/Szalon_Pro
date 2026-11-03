@props(['label', 'collapsible' => false, 'expanded' => false])

@if($collapsible)
<details class="admin-menu-group" @if($expanded) open @endif>
    <summary class="admin-menu-label">{{ $label }}<x-icon name="arrow-right" /></summary>
    {{ $slot }}
</details>
@else
<div class="admin-menu-group" role="group" aria-label="{{ $label }}">
    <p class="admin-menu-label">{{ $label }}</p>
    {{ $slot }}
</div>
@endif
