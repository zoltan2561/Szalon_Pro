<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $formAction = $request->routeIs('admin.google.index') ? "'self' https://accounts.google.com" : "'self'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; base-uri 'self'; form-action {$formAction}; frame-ancestors 'none'");
        if ($request->routeIs('booking.form')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        if ($request->is('admin*', 'kezeles*', 'foglalas/kesz', 'belepes', 'ajandek*', 'ertekeles/lemondas*', 'varolista*', 'szabad-idopont*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }
}
