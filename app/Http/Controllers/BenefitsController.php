<?php

namespace App\Http\Controllers;

use App\Models\BenefitSettings;
use App\Models\Guest;
use App\Models\GuestPass;
use App\Models\PassPlan;
use App\Models\Salon;
use App\Models\Service;
use App\Services\BenefitsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BenefitsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeOwner($request);

        return view('admin.benefits', ['settings' => BenefitSettings::current(), 'services' => Service::orderBy('name')->get(), 'plans' => PassPlan::with('service')->orderBy('name')->get()]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate(['loyalty_points_per_visit' => 'required|integer|min:0|max:100', 'loyalty_reward_threshold' => 'required|integer|min:1|max:1000', 'loyalty_reward_label' => 'required|string|max:120']);
        DB::transaction(function () use ($data): void {
            Salon::whereKey(1)->increment('lock_version');
            BenefitSettings::updateOrCreate(['salon_id' => 1], $data);
        }, 5);

        return back()->with('success', 'A hűségprogram szabályait elmentettük. A korábbi pontok változatlanok.');
    }

    public function savePlan(Request $request, ?PassPlan $passPlan = null): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate(['name' => 'required|string|max:120', 'service_id' => ['required', 'integer', Rule::exists('services', 'id')], 'visits_total' => 'required|integer|min:2|max:100', 'validity_days' => 'nullable|integer|min:1|max:3650']);
        DB::transaction(function () use ($request, $data, $passPlan): void {
            Salon::whereKey(1)->increment('lock_version');
            ($passPlan ?? new PassPlan)->fill($data + ['active' => $request->boolean('active')])->save();
        }, 5);

        return back()->with('success', 'A bérlettípust elmentettük. A már kiadott bérletek feltételei változatlanok.');
    }

    public function saveServicePoints(Request $request, Service $service): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate(['loyalty_points' => 'nullable|integer|min:0|max:100']);
        DB::transaction(function () use ($service, $data): void {
            Salon::whereKey(1)->increment('lock_version');
            $service->update(['loyalty_points' => $data['loyalty_points'] ?? null]);
        }, 5);

        return back()->with('success', 'A szolgáltatás pontszabályát elmentettük.');
    }

    public function issuePass(Request $request, Guest $guest, BenefitsService $benefits): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate(['pass_plan_id' => 'required|integer|exists:pass_plans,id', 'request_key' => 'required|uuid']);
        $benefits->issuePass($guest, PassPlan::findOrFail($data['pass_plan_id']), $request->user(), $data['request_key']);

        return back()->with('success', 'A bérletet kiadtuk a vendégnek. Pénzügyi tranzakció nem történt.');
    }

    public function voidPass(Request $request, Guest $guest, GuestPass $guestPass, BenefitsService $benefits): RedirectResponse
    {
        $this->authorizeOwner($request);
        abort_unless($guestPass->guest_id === $guest->id, 404);
        $request->validate(['confirm' => 'accepted']);
        $benefits->voidPass($guestPass);

        return back()->with('success', 'A bérlet további felhasználását megszüntettük; a korábbi alkalmak megmaradtak.');
    }

    public function redeemReward(Request $request, Guest $guest, BenefitsService $benefits): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate(['request_key' => 'required|uuid']);
        $benefits->redeemReward($guest, $data['request_key']);

        return back()->with('success', 'A hűségjutalmat rögzítettük. A jutalom teljesítését a szalon intézi.');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->role === 'owner', 403);
    }
}
