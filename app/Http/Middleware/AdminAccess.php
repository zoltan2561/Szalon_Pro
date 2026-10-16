<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;

class AdminAccess
{
    public function handle(Request $request, Closure $next, string $role = 'any'): \Symfony\Component\HttpFoundation\Response
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

        return $next($request);
    }
}
