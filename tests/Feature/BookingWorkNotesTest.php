<?php

namespace Tests\Feature;

use App\Console\Commands\BackupSalon;
use App\Models\Booking;
use App\Models\LeaveRequest;
use App\Models\Salon;
use App\Models\User;
use App\Models\WaitlistOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingWorkNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_notes_and_photos_are_private_and_separate_from_the_booking_revision(): void
    {
        Storage::fake('local');
        Salon::factory()->create();
        $booking = Booking::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'owner']));
        $this->get(route('admin.booking', $booking))->assertOk();
        $this->get(route('admin.booking.calendar', $booking))->assertOk();
        $this
            ->post(route('admin.booking.notes', $booking), ['notes_revision' => 1, 'work_notes' => ['formula' => '7.1 / 6%', 'style' => 'Rövid bob'], 'photos' => [UploadedFile::fake()->image('szin.jpg')]])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.booking', $booking));
        $booking->refresh();
        $this->assertSame('7.1 / 6%', $booking->work_notes['formula']);
        $this->assertSame(1, $booking->revision);
        $this->assertSame(2, $booking->notes_revision);
        Storage::disk('local')->assertExists($booking->work_photos[0]);
        $this->get(route('admin.booking.photo', [$booking, 0]))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id, 'can_view_notes' => false]))->get(route('admin.booking.photo', [$booking, 0]))->assertForbidden();
        $this->get(route('admin.booking', $booking))->assertOk()->assertDontSee('7.1 / 6%');
        $this->get(route('booking.manage', $booking->manage_token))->assertOk()->assertDontSee('7.1 / 6%')->assertDontSee('visit-photos');
    }

    public function test_authorized_staff_can_write_only_their_own_visits_and_stale_notes_cannot_overwrite(): void
    {
        Salon::factory()->create();
        $booking = Booking::factory()->create(['work_notes' => ['formula' => 'Megőrzendő'], 'notes_revision' => 2]);
        $staff = User::factory()->create(['role' => 'staff', 'staff_id' => $booking->staff_id, 'can_view_notes' => true]);
        $this->actingAs($staff)->post(route('admin.booking.notes', $booking), ['notes_revision' => 1, 'work_notes' => ['formula' => 'Elavult']])->assertSessionHasErrors('work_notes');
        $this->assertSame('Megőrzendő', $booking->fresh()->work_notes['formula']);
        $this->post(route('admin.booking.notes', $booking), ['notes_revision' => 2, 'work_notes' => ['formula' => 'Új szín']])->assertSessionHasNoErrors();
        $other = Booking::factory()->create();
        $this->post(route('admin.booking.notes', $other), ['notes_revision' => 1])->assertForbidden();
    }

    public function test_anonymizing_a_guest_removes_visit_notes_and_private_photos(): void
    {
        Storage::fake('local');
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'VerySafePassword123']);
        $booking = Booking::factory()->create(['status' => 'completed', 'work_notes' => ['style' => 'Személyes fazon']]);
        $path = 'visit-photos/'.$booking->id.'/reference.jpg';
        Storage::disk('local')->put($path, 'private photo');
        $booking->update(['work_photos' => [$path]]);
        $this->actingAs($owner)->post(route('admin.anonymize', $booking->guest), ['password' => 'VerySafePassword123', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertNull($booking->fresh()->work_notes);
        $this->assertNull($booking->fresh()->work_photos);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_encrypted_backup_restores_private_photos_notes_and_new_workflows(): void
    {
        Storage::fake('local');
        Salon::factory()->create();
        $booking = Booking::factory()->create(['work_notes' => ['formula' => '7.1 / 6%']]);
        $photo = 'visit-photos/'.$booking->id.'/reference.jpg';
        Storage::disk('local')->put($photo, 'private-reference-bytes');
        $booking->update(['work_photos' => [$photo]]);
        LeaveRequest::factory()->create();
        WaitlistOffer::factory()->create();
        $backup = storage_path('framework/workflow-backup-'.Str::uuid().'.backup');
        try {
            $this->artisan('salon:backup', ['--path' => $backup])->assertSuccessful();
            $this->assertStringNotContainsString('private-reference-bytes', file_get_contents($backup));
            foreach (array_reverse(BackupSalon::TABLES) as $table) {
                DB::table($table)->delete();
            }
            Storage::disk('local')->delete($photo);
            $this->artisan('salon:restore', ['path' => $backup])->assertSuccessful();
            $this->assertSame('private-reference-bytes', Storage::disk('local')->get($photo));
            $this->assertSame('7.1 / 6%', Booking::findOrFail($booking->id)->work_notes['formula']);
            $this->assertDatabaseCount('leave_requests', 1);
            $this->assertDatabaseCount('waitlist_offers', 1);
        } finally {
            if (file_exists($backup)) {
                unlink($backup);
            }
        }
    }
}
