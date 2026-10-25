<?php

namespace App\Http\Controllers;

use App\Models\Salon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingPolicyController extends Controller
{
    public function noShow(Request $request): RedirectResponse
    {
        abort_unless(Salon::current()->featureEnabled('no_show_protection'), 404);
        $data = $request->validate(['no_show_limit' => 'required|integer|min:0|max:100', 'cancel_notice_hours' => 'required|integer|min:0|max:168', 'terms' => 'required|string|max:5000']);
        Salon::current()->update($data);

        return back()->with('success', 'A foglalási szabályokat elmentettük.');
    }
}
