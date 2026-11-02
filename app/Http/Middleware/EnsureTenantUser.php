<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && (int) $request->user()->tenant_id !== app(TenantContext::class)->id()) {
            Auth::logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403);
        }

        return $next($request);
    }
}
