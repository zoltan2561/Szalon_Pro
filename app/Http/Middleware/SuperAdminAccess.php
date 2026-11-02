<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_super_admin && (int) $request->user()->tenant_id === 1
            && app(TenantContext::class)->isCentralHost() && ! $request->user()->isDemoViewer(), 403);

        return $next($request);
    }
}
