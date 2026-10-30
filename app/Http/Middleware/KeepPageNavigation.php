<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

class KeepPageNavigation extends StartSession
{
    protected function storeCurrentUrl(Request $request, mixed $session): void
    {
        if ($request->routeIs('booking.calendar', 'admin.booking.calendar', 'admin.booking.photo')) {
            return;
        }

        parent::storeCurrentUrl($request, $session);
    }
}
