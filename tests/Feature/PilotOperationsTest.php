<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PilotOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_password_reset_uses_configuration_and_revokes_only_target_sessions(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'remember_token' => 'old-token']);
        $other = User::factory()->create();
        foreach ([$owner, $other] as $user) {
            DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        }
        config(['salon.admin_password' => 'new-local-reset-password']);
        $this->artisan('salon:reset-password', ['email' => $owner->email])->assertSuccessful();
        $this->assertTrue(Hash::check('new-local-reset-password', $owner->fresh()->password));
        $this->assertNull($owner->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $owner->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
        config(['salon.admin_password' => 'legacy-local-reset-password']);
        $this->artisan('salon:password', ['email' => $owner->email])->assertSuccessful();
        $this->assertTrue(Hash::check('legacy-local-reset-password', $owner->fresh()->password));
    }

    public function test_invalid_password_reset_does_not_change_credentials(): void
    {
        $owner = User::factory()->create();
        $original = $owner->password;
        config(['salon.admin_password' => 'short']);
        $this->artisan('salon:reset-password', ['email' => $owner->email])->assertFailed();
        $this->assertSame($original, $owner->fresh()->password);
        config(['salon.admin_password' => 'new-local-reset-password']);
        $this->artisan('salon:reset-password', ['email' => 'unknown@example.test'])->assertFailed();
        $this->assertSame(1, User::count());
    }

    public function test_hungarian_error_pages_render_without_a_salon_or_database_content(): void
    {
        config(['app.debug' => false]);
        Route::get('/test-error/{status}', fn (int $status) => abort($status, 'private-debug-message'));
        foreach ([404 => 'Az oldal nem található', 419 => 'A munkamenet lejárt', 429 => 'Túl sok kérés érkezett', 500 => 'Átmeneti hiba történt', 503 => 'Rövidesen újra elérhetőek leszünk'] as $status => $title) {
            $response = $this->get('/test-error/'.$status)->assertStatus($status)->assertSee($title)->assertSee('<html lang="hu">', false)->assertDontSee('private-debug-message');
            $this->assertSame(1, substr_count($response->getContent(), '<main'));
            if ($status === 419) {
                $response->assertSee('href="'.route('booking.form').'"', false)->assertSee('Vissza az időpontfoglaláshoz');
            }
        }
        $this->get('/a-missing-page')->assertNotFound()->assertSee('Az oldal nem található');
    }

    public function test_real_salon_has_no_demo_photography_but_keeps_custom_uploads(): void
    {
        $salon = Salon::factory()->create(['demo' => false]);
        foreach (['salon', 'barber', 'nails'] as $theme) {
            $salon->update(['theme' => $theme]);
            $this->get(route('home'))->assertOk()->assertDontSee('AI-generált')->assertDontSee('-hero.webp')->assertDontSee('-studio.webp')->assertSee('hero-without-image');
        }
        $salon->update(['hero_image' => 'branding/own.webp']);
        $this->get(route('home'))->assertOk()->assertSee('storage/branding/own.webp')->assertDontSee('AI-generált')->assertDontSee('hero-without-image');
        $salon->update(['demo' => true, 'hero_image' => null]);
        $this->get(route('home'))->assertOk()->assertSee('nails-hero.webp')->assertSee('AI-generált');
    }
}
