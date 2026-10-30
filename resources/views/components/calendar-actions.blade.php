<details class="calendar-save">
    <summary>Időpont mentése a naptáramba</summary>
    <div class="calendar-save-options">
        @if(!in_array($booking->status, ['cancelled', 'no_show'], true))
            <a class="button secondary" href="{{ app(\App\Services\BookingCalendar::class)->googleUrl($booking) }}" target="_blank" rel="noopener noreferrer">Google Naptár</a>
        @endif
        <a class="button secondary" href="{{ $calendarUrl }}">Apple / Outlook naptár</a>
        @if($booking->series_key)<a class="button secondary" href="{{ $calendarUrl.'?series=1' }}">Teljes sorozat mentése (Apple / Outlook)</a>@endif
    </div>
    <p class="muted">A naptáradban is emlékeztetőt állíthatsz be. Ha áthelyezed vagy lemondod az időpontot, a saját naptáradban is frissítsd.</p>
</details>
