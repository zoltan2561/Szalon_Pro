<div class="booking-inbox-heading"><strong>Új foglalások</strong><span>{{ $bookingInbox['count'] ? $bookingInbox['count'].' olvasatlan' : 'Minden jelzést láttál' }}</span></div>
@if($bookingInbox['items'])
    @if($bookingInbox['count'] > 8)<p class="booking-inbox-hint">A legutóbbi 8 jelzés látható.</p>@endif
    <ul class="booking-inbox-list">
    @foreach($bookingInbox['items'] as $item)
        <li><form method="post" action="{{ route('admin.inbox.open', $item['id']) }}">@csrf<button type="submit" class="booking-inbox-item{{ $item['unread'] ? ' is-unread' : '' }}"><span class="booking-inbox-item-title"><strong>{{ $item['name'] }}</strong>@if($item['unread'])<small>Új</small>@endif</span><span class="booking-inbox-when">{{ $item['when'] }}</span><span>{{ $item['service'] }} · {{ $item['staff'] }}</span><small>{{ $item['status'] }} · Foglalás megnyitása ↗</small></button></form></li>
    @endforeach
    </ul>
    @if($bookingInbox['count'])<form method="post" action="{{ route('admin.inbox.read') }}" class="booking-inbox-read">@csrf<input type="hidden" name="through_id" value="{{ $bookingInbox['through_id'] }}"><button class="text-button" type="submit"><x-icon name="check" /> Mindet láttam</button></form>@endif
@else
    <p class="booking-inbox-empty">Még nincs új online foglalás. Ha egy vendég foglal, itt jelzünk róla.</p>
@endif
<a class="booking-inbox-all" href="{{ route('admin.bookings.index') }}">Összes foglalás <x-icon name="arrow-right" /></a>
