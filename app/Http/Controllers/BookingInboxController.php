<?php

namespace App\Http\Controllers;

use App\Services\BookingInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingInboxController extends Controller
{
    public function index(Request $request, BookingInbox $inbox): JsonResponse
    {
        $summary = $inbox->summary($request->user());

        return response()->json(['count' => $summary['count'], 'through_id' => $summary['through_id'], 'html' => view('components.booking-inbox-panel', ['bookingInbox' => $summary])->render()])->header('Cache-Control', 'no-store, private');
    }

    public function open(Request $request, string $notification, BookingInbox $inbox): RedirectResponse
    {
        return redirect()->route('admin.booking', $inbox->open($request->user(), $notification));
    }

    public function read(Request $request, BookingInbox $inbox): RedirectResponse
    {
        $data = $request->validate(['through_id' => 'required|integer|min:0']);
        $inbox->markRead($request->user(), (int) $data['through_id']);

        return back();
    }
}
