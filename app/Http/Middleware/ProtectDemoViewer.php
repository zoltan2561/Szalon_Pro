<?php

namespace App\Http\Middleware;

use App\Services\DemoAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProtectDemoViewer
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isDemoViewer()) {
            return $next($request);
        }
        if ($request->isMethod('POST') && $request->routeIs('logout')) {
            return $next($request);
        }
        $access = app(DemoAccess::class);
        if ($access->viewer()?->id !== $request->user()->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(404);
        }
        abort_unless($request->isMethodSafe() && $access->allowsRoute((string) $request->route()?->getName()), 403);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
