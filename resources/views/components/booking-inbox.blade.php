<details class="booking-inbox" data-booking-inbox data-inbox-url="{{ route('admin.inbox.index') }}">
    <summary aria-label="Új foglalások: {{ $bookingInbox['count'] }} olvasatlan" title="Új foglalások"><x-icon name="bell" /><span class="booking-inbox-label">Új foglalások</span><span class="booking-inbox-count" data-inbox-count @if(!$bookingInbox['count']) hidden @endif>{{ $bookingInbox['count'] > 99 ? '99+' : $bookingInbox['count'] }}</span></summary>
    <div class="booking-inbox-panel" data-inbox-panel>@include('components.booking-inbox-panel')</div>
</details>
