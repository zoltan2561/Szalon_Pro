<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BookingWorkNotes
{
    public function authorize(Booking $booking, User $user): void
    {
        abort_unless(($user->role === 'owner' || $user->can_view_notes) && Booking::visibleTo($user)->whereKey($booking->id)->exists(), 403);
        abort_if($booking->guest->anonymized_at, 422);
    }

    /** @param array{notes_revision: int, work_notes?: array<string, ?string>, remove_photos?: list<int>} $data
     * @param  list<UploadedFile>  $files
     */
    public function save(Booking $booking, User $user, array $data, array $files): void
    {
        $created = [];
        $removed = [];
        try {
            DB::transaction(function () use ($booking, $user, $data, $files, &$created, &$removed): void {
                Salon::whereKey(1)->increment('lock_version');
                $current = Booking::lockForUpdate()->findOrFail($booking->id);
                $this->authorize($current, $user);
                if ($current->notes_revision !== (int) $data['notes_revision']) {
                    throw ValidationException::withMessages(['work_notes' => 'A munkalap közben megváltozott. Nyisd meg újra, hogy a másik munkatárs jegyzete is megmaradjon.']);
                }
                $photos = $current->work_photos ?? [];
                $removed = array_values(array_filter($photos, fn (string $path, int $index): bool => in_array($index, array_map('intval', $data['remove_photos'] ?? []), true), ARRAY_FILTER_USE_BOTH));
                $kept = array_values(array_diff($photos, $removed));
                if (count($kept) + count($files) > 4) {
                    throw ValidationException::withMessages(['photos' => 'Egy alkalomhoz legfeljebb 4 fotót őrizhetsz meg. Előbb jelöld a törlendő képeket.']);
                }
                foreach ($files as $file) {
                    if (count($created) === count($files)) {
                        break;
                    }
                    $path = $file->store('visit-photos/'.$booking->id, 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['photos' => 'A fotót nem sikerült elmenteni. Próbáld újra.']);
                    }
                    $created[] = $path;
                }
                $current->update(['work_notes' => $data['work_notes'] ?? [], 'work_photos' => [...$kept, ...$created], 'notes_revision' => $current->notes_revision + 1, 'notes_updated_at' => now(), 'notes_updated_by_id' => $user->id]);
            }, 5);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($created);
            throw $error;
        }
        Storage::disk('local')->delete($removed);
    }

    public function eraseForGuest(Guest $guest): void
    {
        $paths = $guest->bookings()->get()->flatMap(fn (Booking $booking): array => $booking->work_photos ?? [])->all();
        $guest->bookings()->update(['work_notes' => null, 'work_photos' => null, 'notes_updated_by_id' => null, 'notes_updated_at' => null, 'notes_revision' => DB::raw('notes_revision + 1')]);
        DB::afterCommit(fn () => Storage::disk('local')->delete($paths));
    }
}
