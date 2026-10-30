<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingWorkNotes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingWorkNotesController extends Controller
{
    public function store(Booking $booking, Request $request, BookingWorkNotes $notes): RedirectResponse
    {
        $notes->authorize($booking, $request->user());
        $data = $request->validate([
            'notes_revision' => 'required|integer|min:1',
            'work_notes' => 'nullable|array:formula,products,style,note',
            'work_notes.*' => 'nullable|string|max:1500',
            'photos' => 'nullable|array|max:4',
            'photos.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'remove_photos' => 'nullable|array|max:4',
            'remove_photos.*' => 'integer|between:0,3',
        ]);
        $notes->save($booking, $request->user(), $data, $request->file('photos', []));

        return back()->with('success', 'A látogatás munkalapját elmentettük.');
    }

    public function photo(Booking $booking, int $photo, Request $request, BookingWorkNotes $notes): StreamedResponse
    {
        $notes->authorize($booking, $request->user());
        $path = ($booking->work_photos ?? [])[$photo] ?? null;
        abort_unless(is_string($path) && preg_match('#^visit-photos/'.$booking->id.'/[A-Za-z0-9]+\.(jpg|jpeg|png|webp)$#', $path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: ['Cache-Control' => 'private, no-store']);
    }
}
