<div class="calendar-legend" aria-label="Naptár állapotainak jelmagyarázata">
    <span class="calendar-legend-label">Állapotok</span>
    @foreach(['confirmed' => 'Visszaigazolt', 'completed' => 'Elvégezve', 'no_show' => 'Nem jelent meg', 'cancelled' => 'Lemondva'] as $status => $label)
        <span class="calendar-status {{ $status }}">{{ $label }}</span>
    @endforeach
    <span class="calendar-status unavailable">Blokkolt / Google-foglalt</span>
</div>
