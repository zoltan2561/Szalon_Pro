<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use App\Services\OperationsHealth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAccess
{
    public function handle(Request $request, Closure $next, string $role = 'any'): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }
        if ($request->user()->role !== 'owner') {
            abort_unless(Staff::whereKey($request->user()->staff_id)->where('active', true)->exists(), 403);
        }
        if ($role === 'owner') {
            abort_unless($request->user()->role === 'owner', 403);
        }

        if (! app(OperationsHealth::class)->schemaReady()) {
            return response()->view('admin.maintenance', [], 503);
        }

        return $next($request);
    }
}
