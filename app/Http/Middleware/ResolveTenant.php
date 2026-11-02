<?php

namespace App\Http\Middleware;

use App\Models\Salon;
use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $host = strtolower($request->getHost());
        $demoHosts = array_filter(array_map(fn (string $url): ?string => parse_url($url, PHP_URL_HOST), config('salon.demo_urls', [])));
        $isLegacy = $context->isCentralHost($host) || in_array($host, array_merge(config('tenancy.demo_domains'), $demoHosts), true);
        $tenant = $isLegacy ? Tenant::find(1) : Tenant::where('domain', $host)->first();
        $salon = $tenant ? Salon::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first(['name', 'phone', 'email']) : null;
        $request->attributes->set('error_contact', ['errorSalonName' => $salon?->name, 'errorPhone' => $salon?->phone, 'errorEmail' => $salon?->email]);
        abort_unless($tenant, 404);
        abort_unless($tenant->status === 'active', 503);
        $originalCookie = config('session.cookie');
        $originalDomain = config('session.domain');
        config(['session.domain' => null, 'session.cookie' => $originalCookie.($tenant->id === 1 ? '' : '_tenant_'.$tenant->id)]);
        try {
            return $context->run($tenant, fn () => $next($request), $tenant->id === 1 ? $request->root() : $tenant->url());
        } finally {
            config(['session.cookie' => $originalCookie, 'session.domain' => $originalDomain]);
        }
    }
}
