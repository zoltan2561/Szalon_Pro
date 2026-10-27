<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarRelay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCalendarRelayController extends Controller
{
    public function __invoke(Request $request, GoogleCalendarRelay $relay): RedirectResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');
        $error = $request->query('error');
        abort_unless(is_string($state) && ($callback = $relay->callback($state)), 400);
        abort_unless((is_string($code) && $code !== '' && strlen($code) <= 4096 && $error === null)
            || (is_string($error) && $error !== '' && strlen($error) <= 100 && $code === null), 400);
        $query = ['state' => $state] + (is_string($code) ? ['code' => $code] : ['error' => $error]);

        return redirect()->away($callback.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986))
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
