<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $filament = $request->is('foadmin*', 'szalon-admin*', 'livewire*');
        $nonce = $filament ? base64_encode(random_bytes(24)) : null;
        if ($nonce) {
            Vite::useCspNonce($nonce);
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $formAction = $request->routeIs('admin.google.index') ? "'self' https://accounts.google.com" : "'self'";
        if ($filament) {
            $formAction .= ' '.rtrim(config('app.url'), '/');
        }
        $scripts = $nonce ? "'self' 'nonce-{$nonce}' 'unsafe-eval'" : "'self'";
        $response->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src {$scripts}; base-uri 'self'; form-action {$formAction}; frame-ancestors 'none'");
        if ($request->routeIs('booking.form')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        if ($filament || $request->is('admin*', 'kezeles*', 'foglalas/kesz', 'belepes', 'ajandek*', 'ertekeles/lemondas*', 'varolista*', 'szabad-idopont*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }
}
