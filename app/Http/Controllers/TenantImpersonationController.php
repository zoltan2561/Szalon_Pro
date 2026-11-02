<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantImpersonationController extends Controller
{
    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor?->is_super_admin && (int) $actor->tenant_id === 1
            && ! $actor->isDemoViewer() && app(TenantContext::class)->isCentralHost(), 403);
        abort_unless($tenant->status === 'active', 409);
        $owner = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'owner')->where('is_demo_viewer', false)->firstOrFail();
        $token = bin2hex(random_bytes(32));

        DB::table('tenant_impersonations')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'actor_id' => $actor->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
        ]);

        return redirect()->away($tenant->url().'/foadmin/atvetel/'.$token)
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);
        $context = app(TenantContext::class);
        [$owner, $actorId] = DB::transaction(function () use ($context, $token): array {
            $ticket = DB::table('tenant_impersonations')->where('token_hash', hash('sha256', $token))
                ->where('tenant_id', $context->id())->whereNull('used_at')->where('expires_at', '>', now())->lockForUpdate()->first();
            abort_unless($ticket, 404);
            $tenant = Tenant::findOrFail($ticket->tenant_id);
            abort_unless($tenant->status === 'active', 404);
            $actor = User::withoutGlobalScopes()->find($ticket->actor_id);
            abort_unless($actor?->is_super_admin && (int) $actor->tenant_id === 1 && ! $actor->isDemoViewer(), 404);
            $owner = User::whereKey($ticket->user_id)->where('role', 'owner')->where('is_demo_viewer', false)->firstOrFail();
            $updated = DB::table('tenant_impersonations')->where('token_hash', hash('sha256', $token))->whereNull('used_at')->update(['used_at' => now()]);
            abort_unless($updated === 1, 404);

            return [$owner, $actor->id];
        }, 5);

        Auth::guard()->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', Auth::guard()->hashPasswordForCookie($owner->getAuthPassword()));
        $request->session()->put('impersonator_id', $actorId);

        return redirect('/szalon-admin')->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']);
    }

    public function stop(Request $request): RedirectResponse
    {
        abort_unless($request->session()->has('impersonator_id'), 403);
        Auth::guard()->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away(rtrim((string) config('app.url'), '/').'/foadmin/tenants');
    }
}
