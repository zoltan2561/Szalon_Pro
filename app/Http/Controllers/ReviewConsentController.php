<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Services\ReviewRequests;
use Illuminate\Contracts\View\View;

class ReviewConsentController extends Controller
{
    public function show(Guest $guest): View
    {
        return view('public.review-consent', ['unsubscribed' => false]);
    }

    public function unsubscribe(Guest $guest, ReviewRequests $reviews): View
    {
        $reviews->unsubscribe($guest);

        return view('public.review-consent', ['unsubscribed' => true]);
    }
}
