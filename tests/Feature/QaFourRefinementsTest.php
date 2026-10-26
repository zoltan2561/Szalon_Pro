<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QaFourRefinementsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{bool}> */
    public static function staffForms(): array
    {
        return ['existing' => [true], 'new' => [false]];
    }

    #[DataProvider('staffForms')]
    public function test_failed_staff_form_preserves_text_only_in_the_affected_card(bool $existing): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        User::factory()->create(['role' => 'owner', 'email' => 'taken@example.test']);
        $staff = Staff::factory()->create(['name' => 'Eredeti név', 'title' => 'Fodrász']);
        $data = ['name' => 'Megőrzött Név', 'title' => 'Mesterfodrász', 'bio' => 'Megőrzött bemutatkozás', 'email' => 'taken@example.test', 'password' => 'do-not-render-password', 'active' => 1, 'hours' => [1 => ['start' => '08:30', 'end' => '17:30']]];
        $this->actingAs($owner)->from(route('admin.staff'))->post(route('admin.staff.save', $existing ? $staff : null), $data)->assertSessionHasErrors('email');
        $response = $this->get(route('admin.staff'))->assertOk()->assertSee('value="Megőrzött Név"', false)->assertSee('value="Mesterfodrász"', false)->assertSee('value="taken@example.test"', false)->assertSee('Megőrzött bemutatkozás')->assertDontSee('do-not-render-password');
        $this->assertSame(1, substr_count($response->getContent(), 'value="Mesterfodrász"'));
        $this->assertSame('Fodrász', $staff->fresh()->title);
        $this->assertSame(1, Staff::count());
    }

    public function test_content_rights_are_restored_persisted_and_required_again_for_new_images(): void
    {
        Storage::fake('public');
        $salon = Salon::factory()->create(['published' => false]);
        $owner = User::factory()->create(['role' => 'owner']);
        $data = $this->settingsData($salon);
        $this->actingAs($owner)->post(route('admin.settings.save'), $data + ['content_rights_accepted_at' => now()->toDateTimeString()])->assertSessionHasErrors('content_rights');
        $this->from(route('admin.settings'))->post(route('admin.settings.save'), array_replace($data, ['name' => '', 'content_rights' => 1]))->assertSessionHasErrors('name');
        $this->get(route('admin.settings'))->assertOk()->assertSee('name="content_rights" value="1" checked', false);
        $this->post(route('admin.settings.save'), $data + ['content_rights' => 1])->assertSessionHasNoErrors();
        $accepted = $salon->fresh()->content_rights_accepted_at;
        $this->assertNotNull($accepted);
        $this->post(route('admin.settings.save'), $data)->assertSessionHasNoErrors();
        $this->assertEquals($accepted, $salon->fresh()->content_rights_accepted_at);
        $photo = UploadedFile::fake()->image('new-photo.png');
        $this->post(route('admin.settings.save'), $data + ['hero_image' => $photo])->assertSessionHasErrors('content_rights');
        $this->post(route('admin.settings.save'), $data + ['hero_image' => $photo, 'content_rights' => 1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($salon->fresh()->hero_image);
    }

    /** @return array<string, array{string, bool}> */
    public static function phoneNumbers(): array
    {
        return [
            'international zeros' => ['+36 00 000 0000', false],
            'domestic zeros' => ['06 00 000 0000', false],
            'international dial prefix' => ['0036 00 000 0000', false],
            'Budapest zeros' => ['+36 1 000 0000', false],
            'mobile zeros' => ['+36 30 000 0000', false],
            'other mobile zeros' => ['06 70 000 0000', false],
            'valid mobile' => ['+36 30 123 4567', true],
            'valid Budapest' => ['06 1 234 5678', true],
            'valid dial prefix' => ['0036 70 123 4567', true],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_publication_rejects_zero_subscribers_after_normalizing_hungarian_prefixes(string $phone, bool $allowed): void
    {
        $salon = Salon::factory()->create(['published' => false]);
        Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $data = array_replace($this->settingsData($salon), ['phone' => $phone, 'published' => 1, 'content_rights' => 1]);
        $response = $this->actingAs($owner)->post(route('admin.settings.save'), $data);
        if ($allowed) {
            $response->assertSessionHasNoErrors();
        } else {
            $response->assertSessionHasErrors('phone');
        }
        $this->assertSame($allowed, $salon->fresh()->published);
    }

    public function test_error_pages_use_available_salon_contact_and_survive_missing_settings(): void
    {
        config(['app.debug' => false]);
        $salon = Salon::factory()->create(['name' => 'Saját Szalon', 'phone' => '+36301234567', 'email' => 'hello@example.test']);
        Route::get('/test-server-error', fn () => abort(500));
        $this->get('/test-server-error')->assertStatus(500)->assertSee('Átmeneti hiba történt · Saját Szalon')->assertSee($salon->phone)->assertSee($salon->email);
        Schema::drop('salons');
        $this->get('/test-server-error')->assertStatus(500)->assertSee('Átmeneti hiba történt · SzalonPRO')->assertDontSee('hello@example.test');
    }

    public function test_unpublished_privacy_is_private_except_for_owner_preview(): void
    {
        Salon::factory()->create(['published' => false]);
        $this->get(route('privacy'))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('privacy'))->assertOk();
    }

    public function test_cancelled_mail_has_no_rescheduling_terms_in_either_part(): void
    {
        $salon = Salon::factory()->create(['terms' => 'Egyedi 24 órás áthelyezési szabály']);
        $salon->refresh();
        $booking = Booking::factory()->create(['status' => 'cancelled']);
        foreach (['emails.booking', 'emails.booking-text'] as $view) {
            $body = view($view, ['salon' => $salon, 'booking' => $booking, 'kind' => 'cancelled'])->render();
            $this->assertStringNotContainsString($salon->terms, $body);
            $this->assertStringContainsString('Új időpont', $body);
        }
    }

    public function test_scheduler_includes_weekly_backup_pruning_and_command_descriptions_are_hungarian(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('salon:prune-backups --days=30')->assertSuccessful();
        $commands = Artisan::all();
        $this->assertSame('Kapcsolt Google-naptárak foglalásainak és külső foglaltságának frissítése', $commands['salon:google-sync']->getDescription());
        $this->assertSame('Esedékes SMS-emlékeztetők küldése bekapcsolt SMS-modulnál', $commands['salon:sms-notify']->getDescription());
    }

    /** @return array<string, mixed> */
    private function settingsData(Salon $salon): array
    {
        $salon->refresh();

        return $salon->only(['name', 'tagline', 'about', 'email', 'phone', 'address', 'theme', 'accent', 'font', 'terms', 'privacy', 'min_notice_minutes', 'horizon_days', 'cancel_notice_hours', 'reminder_hours', 'retention_days']) + ['hours' => [1 => ['start' => '09:00', 'end' => '18:00']]];
    }
}
