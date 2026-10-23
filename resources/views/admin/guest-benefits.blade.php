@if(auth()->user()->role === 'owner' && !$guest->anonymized_at && $salon->featureEnabled('passes'))
    <section class="panel">
        <p class="eyebrow">BÉRLETEK</p>
        <h2>Felhasználható alkalmak</h2>
        @forelse($guestPasses as $guestPass)
            <div class="history-row">
                <div>
                    <strong>{{ $guestPass->name }}</strong>
                    <p>{{ $guestPass->service->name }} · {{ $guestPass->visits_remaining }}/{{ $guestPass->visits_total }} alkalom @if($guestPass->expires_at) · lejár: {{ $guestPass->expires_at->setTimezone($salon->timezone)->format('Y. m. d.') }} @endif</p>
                </div>
                <span class="badge {{ $guestPass->isUsable() ? 'completed' : 'cancelled' }}">{{ $guestPass->voided_at ? 'Visszavonva' : ($guestPass->isUsable() ? 'Felhasználható' : 'Lejárt / elfogyott') }}</span>
            </div>
            @if(auth()->user()->role === 'owner' && !$guestPass->voided_at && $guestPass->visits_remaining > 0)
                <form method="post" action="{{ route('admin.benefits.void', [$guest, $guestPass]) }}">
                    @csrf
                    <label class="check"><input type="checkbox" name="confirm" value="1" required><span>A bérlet további felhasználását megszüntetem.</span></label>
                    <button class="button danger small">Bérlet visszavonása</button>
                </form>
            @endif
        @empty
            <p class="muted">Ennek a vendégnek még nincs bérlete.</p>
        @endforelse
        @if(auth()->user()->role === 'owner' && $passPlans->isNotEmpty())
            <hr>
            <form method="post" action="{{ route('admin.benefits.issue', $guest) }}">
                @csrf
                <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <label>Új bérlet kiadása<select name="pass_plan_id" required>
                    @foreach($passPlans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }} · {{ $plan->service->name }} · {{ $plan->visits_total }} alkalom</option>
                    @endforeach
                </select></label>
                <p class="muted">A kiadás alkalmakat rögzít; pénzügyi műveletet nem végez.</p>
                <button class="button secondary">Bérlet kiadása</button>
            </form>
        @endif
    </section>
@endif

@if(auth()->user()->role === 'owner' && !$guest->anonymized_at && $salon->featureEnabled('loyalty'))
    <section class="panel">
        <p class="eyebrow">HŰSÉGPONTOK</p>
        <h2>{{ $loyaltyBalance }} pont</h2>
        <p class="muted">{{ $benefitSettings->loyalty_reward_threshold }} pont = {{ $benefitSettings->loyalty_reward_label }}. Pont csak elvégzett látogatás után jár.</p>
        @if(auth()->user()->role === 'owner' && $loyaltyBalance >= $benefitSettings->loyalty_reward_threshold)
            <form method="post" action="{{ route('admin.benefits.redeem', $guest) }}">
                @csrf
                <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <button class="button secondary full">Jutalom beváltásának rögzítése</button>
            </form>
        @endif
        @if($loyaltyEntries->isNotEmpty())
            <hr>
            @foreach($loyaltyEntries as $entry)
                <div class="history-row"><div><strong>{{ $entry->description }}</strong><p>{{ $entry->created_at->setTimezone($salon->timezone)->format('Y. m. d. H:i') }}</p></div><span class="badge {{ $entry->points_delta >= 0 ? 'completed' : 'cancelled' }}">{{ $entry->points_delta > 0 ? '+' : '' }}{{ $entry->points_delta }} pont</span></div>
            @endforeach
        @endif
    </section>
@endif
