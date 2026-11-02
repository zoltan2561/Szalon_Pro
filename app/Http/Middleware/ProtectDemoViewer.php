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
        if ($request->isMethodSafe() && $request->routeIs('login')) {
            return redirect()->route('admin.today');
        }
        if (! $request->isMethodSafe() || ! $access->allowsRoute((string) $request->route()?->getName())) {
            $description = match (true) {
                $request->routeIs('admin.google.*') => 'Itt kötöd össze a munkatársak Google Naptárát.',
                $request->routeIs('admin.notifications') => 'Itt állítod be és ellenőrzöd az értesítéseket.',
                $request->routeIs('admin.settings', 'admin.settings.*') => 'Itt állítod be a saját szalonod arculatát, adatait és nyitvatartását.',
                $request->routeIs('admin.account', 'admin.account.*') => 'Itt kezeled a saját fiókodat és jelszavadat.',
                default => 'A bemutatóban a mintaadatok védettek, ez a művelet nem használható.',
            };

            return response()->view('admin.demo-restricted', ['description' => $description], 403)->header('Cache-Control', 'no-store, private');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
