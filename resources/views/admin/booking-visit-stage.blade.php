@if($booking->status === 'confirmed' && (auth()->user()->role === 'owner' || auth()->user()->can_manage_bookings) && $booking->starts_at->setTimezone($salon->timezone)->isSameDay(now($salon->timezone)))
<section class="panel visit-stage">
    <p class="eyebrow">HOL TART A LÁTOGATÁS?</p><h2>{{ $booking->visitLabel() }}</h2>
    <form method="post" action="{{ route('admin.booking.update', $booking) }}">@csrf<input type="hidden" name="revision" value="{{ $booking->revision }}">
        <div class="actions">
            @unless($booking->arrived_at)<button class="button secondary" name="action" value="arrive">A vendég megérkezett</button>@endunless
            @unless($booking->started_at)<button class="button" name="action" value="start">Munka indítása</button>@endunless
        </div>
        @if($booking->started_at)<p class="muted">Kezdés: {{ $booking->started_at->setTimezone($salon->timezone)->format('H:i') }}. Amikor végeztél, zárd le a látogatást az Elvégezve gombbal.</p>@endif
    </form>
</section>
@endif
