<?php

namespace App\Services;

use App\Models\BenefitSettings;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\GuestPass;
use App\Models\LoyaltyEntry;
use App\Models\PassPlan;
use App\Models\PassUse;
use App\Models\Salon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BenefitsService
{
    public function completeBooking(Booking $booking, ?int $guestPassId = null): void
    {
        DB::transaction(function () use ($booking, $guestPassId): void {
            Salon::whereKey(1)->increment('lock_version');
            $booking = Booking::with('service', 'guest')->findOrFail($booking->id);
            if ($booking->status !== 'completed') {
                $this->error('Csak elvégzett foglaláshoz számolható el bérlet vagy hűségpont.');
            }
            if ($booking->guest->anonymized_at) {
                if ($guestPassId !== null) {
                    $this->error('Törölt vendégkartonon bérlet nem használható.');
                }

                return;
            }

            $existingUse = PassUse::where('booking_id', $booking->id)->first();
            if ($existingUse?->reversed_at) {
                $this->error('A foglalás korábbi bérletelszámolását már visszavontuk.');
            }
            if ($existingUse && $guestPassId !== null && $existingUse->guest_pass_id !== $guestPassId) {
                $this->error('Ehhez a foglaláshoz már másik bérletet használtak.');
            }

            $salon = Salon::current();
            if ($guestPassId !== null && ! $existingUse) {
                if (! $salon->featureEnabled('passes')) {
                    $this->error('A bérletmodul ki van kapcsolva.');
                }
                $guestPass = GuestPass::findOrFail($guestPassId);
                if ($guestPass->guest_id !== $booking->guest_id || $guestPass->service_id !== $booking->service_id || ! $guestPass->isUsable()) {
                    $this->error('A kiválasztott bérlet nem használható ehhez a foglaláshoz.');
                }
                if (GuestPass::whereKey($guestPass->id)->where('visits_remaining', '>', 0)->whereNull('voided_at')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->decrement('visits_remaining') !== 1) {
                    $this->error('A bérleten már nincs felhasználható alkalom.');
                }
                PassUse::create(['guest_pass_id' => $guestPass->id, 'booking_id' => $booking->id]);
            }

            if ($salon->featureEnabled('loyalty') && ! LoyaltyEntry::where('request_key', 'booking:'.$booking->id.':earned')->exists()) {
                $points = $booking->service->loyalty_points ?? BenefitSettings::current()->loyalty_points_per_visit;
                LoyaltyEntry::create(['guest_id' => $booking->guest_id, 'booking_id' => $booking->id, 'kind' => 'earned', 'points_delta' => $points, 'description' => 'Elvégzett látogatás: '.$booking->service_name, 'request_key' => 'booking:'.$booking->id.':earned']);
            }
        }, 5);
    }

    public function reverseBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            Salon::whereKey(1)->increment('lock_version');
            $booking = Booking::findOrFail($booking->id);
            if ($booking->status === 'completed') {
                $this->error('Előbb vond vissza a foglalás elvégzett állapotát.');
            }

            $use = PassUse::where('booking_id', $booking->id)->whereNull('reversed_at')->first();
            if ($use) {
                $use->update(['reversed_at' => now()]);
                GuestPass::whereKey($use->guest_pass_id)->increment('visits_remaining');
            }
            $earned = LoyaltyEntry::where('request_key', 'booking:'.$booking->id.':earned')->first();
            if ($earned) {
                LoyaltyEntry::firstOrCreate(['request_key' => 'booking:'.$booking->id.':reversed'], ['guest_id' => $earned->guest_id, 'booking_id' => $booking->id, 'kind' => 'reversed', 'points_delta' => -$earned->points_delta, 'description' => 'Látogatás jóváírásának visszavonása']);
            }
        }, 5);
    }

    public function issuePass(Guest $guest, PassPlan $plan, User $actor, string $requestKey): GuestPass
    {
        abort_unless($actor->role === 'owner', 403);

        return DB::transaction(function () use ($guest, $plan, $actor, $requestKey): GuestPass {
            Salon::whereKey(1)->increment('lock_version');
            $existing = GuestPass::where('issue_key', $requestKey)->first();
            if ($existing) {
                if ($existing->guest_id !== $guest->id || $existing->pass_plan_id !== $plan->id) {
                    $this->error('Ez a kiadási azonosító már másik bérlethez tartozik.');
                }

                return $existing;
            }
            if (! Salon::current()->featureEnabled('passes') || ! $plan->active || ! $plan->service->active || $guest->anonymized_at) {
                $this->error('Ehhez a vendéghez ez a bérlet most nem adható ki.');
            }

            return GuestPass::create(['guest_id' => $guest->id, 'pass_plan_id' => $plan->id, 'service_id' => $plan->service_id, 'issued_by_user_id' => $actor->id, 'name' => $plan->name, 'visits_total' => $plan->visits_total, 'visits_remaining' => $plan->visits_total, 'expires_at' => $plan->validity_days ? CarbonImmutable::now(Salon::current()->timezone)->addDays($plan->validity_days)->utc() : null, 'issue_key' => $requestKey]);
        }, 5);
    }

    public function voidPass(GuestPass $guestPass): void
    {
        DB::transaction(function () use ($guestPass): void {
            Salon::whereKey(1)->increment('lock_version');
            $guestPass->refresh();
            if (! $guestPass->voided_at) {
                $guestPass->update(['voided_at' => now()]);
            }
        }, 5);
    }

    public function redeemReward(Guest $guest, string $requestKey): LoyaltyEntry
    {
        return DB::transaction(function () use ($guest, $requestKey): LoyaltyEntry {
            Salon::whereKey(1)->increment('lock_version');
            $key = 'reward:'.$requestKey;
            $existing = LoyaltyEntry::where('request_key', $key)->first();
            if ($existing) {
                if ($existing->guest_id !== $guest->id) {
                    $this->error('Ez a jutalomazonosító már másik vendéghez tartozik.');
                }

                return $existing;
            }
            if (! Salon::current()->featureEnabled('loyalty') || $guest->anonymized_at) {
                $this->error('A hűségpontok most nem válthatók be.');
            }
            $settings = BenefitSettings::current();
            if ($this->loyaltyBalance($guest) < $settings->loyalty_reward_threshold) {
                $this->error('Nincs elég pont a jutalom beváltásához.');
            }

            return LoyaltyEntry::create(['guest_id' => $guest->id, 'kind' => 'reward', 'points_delta' => -$settings->loyalty_reward_threshold, 'description' => $settings->loyalty_reward_label, 'request_key' => $key]);
        }, 5);
    }

    public function loyaltyBalance(Guest $guest): int
    {
        return (int) LoyaltyEntry::where('guest_id', $guest->id)->sum('points_delta');
    }

    public function usablePassesFor(Booking $booking): Collection
    {
        if (! Salon::current()->featureEnabled('passes') || $booking->guest->anonymized_at) {
            return new Collection;
        }

        return GuestPass::where('guest_id', $booking->guest_id)->where('service_id', $booking->service_id)->where('visits_remaining', '>', 0)->whereNull('voided_at')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderBy('expires_at')->get();
    }

    /**
     * @return array{benefitSettings: BenefitSettings, guestPasses: Collection, passPlans: Collection, loyaltyEntries: Collection, loyaltyBalance: int}
     */
    public function guestData(Guest $guest): array
    {
        return ['benefitSettings' => BenefitSettings::current(), 'guestPasses' => GuestPass::with('service')->where('guest_id', $guest->id)->orderByDesc('id')->get(), 'passPlans' => PassPlan::with('service')->where('active', true)->whereHas('service', fn ($query) => $query->where('active', true))->orderBy('name')->get(), 'loyaltyEntries' => LoyaltyEntry::where('guest_id', $guest->id)->orderByDesc('id')->limit(20)->get(), 'loyaltyBalance' => $this->loyaltyBalance($guest)];
    }

    private function error(string $message): never
    {
        throw ValidationException::withMessages(['benefits' => $message]);
    }
}
