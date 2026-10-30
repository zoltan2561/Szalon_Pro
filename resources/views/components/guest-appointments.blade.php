@forelse($appointments as $appointment)
<div class="history-row"><div><strong>{{ $appointment->starts_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }}</strong><p>{{ $appointment->service_name }} · {{ $appointment->staff->name }}@if($salon->featureEnabled('financials')) · {{ number_format($appointment->price, 0, ',', ' ') }} Ft @endif</p></div><span class="badge {{ $appointment->status }}">{{ $appointment->visitLabel() }}</span><a href="{{ route('admin.booking', $appointment) }}">Megnyitás ↗</a></div>
@empty
<p class="muted">{{ $emptyMessage }}</p>
@endforelse
