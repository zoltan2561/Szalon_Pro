<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function settingsData(Salon $salon, array $appearance): array
    {
        $salon->refresh();

        return $salon->only(['name', 'tagline', 'about', 'email', 'phone', 'address', 'theme', 'accent', 'font', 'terms', 'privacy', 'min_notice_minutes', 'horizon_days', 'cancel_notice_hours', 'reminder_hours', 'retention_days']) + ['hours' => array_fill(1, 7, ['start' => '09:00', 'end' => '18:00']), 'content_rights' => 1, 'appearance' => $appearance, 'current_password' => '', 'new_password' => ''];
    }

    public function test_three_presets_persist_and_preserve_business_content_and_custom_media(): void
    {
        $salon = Salon::factory()->create(['name' => 'Saját márkanév', 'hero_image' => 'branding/own.webp', 'logo' => 'branding/logo.png']);
        $service = Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get(route('admin.settings'))->assertOk()->assertSee('Főoldali elrendezés')->assertSee('name="hero_image"', false)->assertDontSee("@include('admin.appearance')");
        foreach (['barber', 'salon', 'nails'] as $key) {
            $this->actingAs($owner)->post(route('admin.branding.preset'), ['preset' => $key])->assertRedirect()->assertSessionHasNoErrors();
            $salon->refresh();
            $this->assertSame($key, $salon->theme);
            $this->assertSame(config('branding.presets.'.$key.'.accent'), $salon->accent);
            $this->assertSame('split', $salon->appearance['layout']);
            $this->assertSame('Saját márkanév', $salon->name);
            $this->assertSame('branding/own.webp', $salon->hero_image);
            $this->assertSame('branding/logo.png', $salon->logo);
            $this->assertSame($service->name, $service->fresh()->name);
            $this->get(route('home'))->assertOk()->assertSee('storage/branding/own.webp')->assertSee('Saját márkanév')->assertSee('layout-split');
        }
    }

    public function test_staff_cannot_change_presets_and_invalid_presets_do_not_mutate_branding(): void
    {
        $salon = Salon::factory()->create();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->post(route('admin.branding.preset'), ['preset' => 'nails'])->assertForbidden();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->post(route('admin.branding.preset'), ['preset' => '../invalid'])->assertSessionHasErrors('preset');
        $this->assertSame('salon', $salon->fresh()->theme);
    }

    public function test_custom_appearance_and_uploaded_hero_are_saved_and_rendered(): void
    {
        Storage::fake('public');
        $salon = Salon::factory()->create();
        Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $appearance = config('branding.presets.salon.appearance');
        $appearance['layout'] = 'immersive';
        $appearance['paper'] = '#FFFFFF';
        $appearance['cta_label'] = 'Foglalok magamnak';
        unset($appearance['show_team']);
        $data = $this->settingsData($salon, $appearance) + ['published' => 1, 'hero_image' => UploadedFile::fake()->image('hero.png', 1600, 1000)];
        $this->actingAs($owner)->post(route('admin.settings.save'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $salon->refresh();
        $this->assertFalse($salon->appearance['show_team']);
        $this->assertSame('immersive', $salon->appearance['layout']);
        Storage::disk('public')->assertExists($salon->hero_image);
        $this->get(route('home'))->assertOk()->assertSee('layout-immersive')->assertSee('--paper:#FFFFFF', false)->assertSee('Foglalok magamnak')->assertSee('storage/'.$salon->hero_image)->assertDontSee('Akik várnak rád');
        $this->post(route('admin.settings.save'), $this->settingsData($salon, $appearance) + ['published' => 1, 'reset_hero' => 1])->assertSessionHasNoErrors();
        $this->assertNull($salon->fresh()->hero_image);
        $this->get(route('home'))->assertSee('hair-hero.webp');
    }

    public function test_unsafe_css_and_low_contrast_changes_are_rejected_without_changing_saved_theme(): void
    {
        $salon = Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $appearance = config('branding.presets.salon.appearance');
        $appearance['ink'] = '#FAF8F2';
        $this->actingAs($owner)->post(route('admin.settings.save'), $this->settingsData($salon, $appearance))->assertSessionHasErrors('appearance.ink');
        $this->assertNull($salon->fresh()->appearance);
        $appearance['ink'] = 'red;position:fixed';
        $this->post(route('admin.settings.save'), $this->settingsData($salon, $appearance))->assertSessionHasErrors('appearance.ink');
        $this->assertNull($salon->fresh()->appearance);
        $appearance = config('branding.presets.salon.appearance');
        $this->post(route('admin.settings.save'), $this->settingsData($salon, $appearance) + ['hero_image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('hero_image');
    }

    public function test_appearance_changes_cannot_replace_password_without_current_password(): void
    {
        $salon = Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $originalHash = $owner->password;
        $data = array_replace($this->settingsData($salon, config('branding.presets.salon.appearance')), ['new_password' => 'new-demo-test-password']);
        $this->actingAs($owner)->post(route('admin.settings.save'), $data)->assertSessionHasErrors('current_password');
        $this->assertSame($originalHash, $owner->fresh()->password);
    }

    public function test_preset_defaults_have_readable_text_and_automatic_button_contrast(): void
    {
        $branding = app(Branding::class);
        foreach (config('branding.presets') as $preset) {
            foreach (['paper', 'surface'] as $background) {
                foreach (['ink', 'muted'] as $text) {
                    $this->assertGreaterThanOrEqual(4.5, $branding->contrast($preset['appearance'][$background], $preset['appearance'][$text]));
                }
            }
            $this->assertGreaterThanOrEqual(4.5, $branding->contrast($preset['accent'], $branding->foreground($preset['accent'])));
        }
    }

    public function test_nails_demo_installs_a_dedicated_catalog_and_resists_reinstallation(): void
    {
        putenv('SALON_ADMIN_PASSWORD=temporary-demo-password');
        $_ENV['SALON_ADMIN_PASSWORD'] = 'temporary-demo-password';
        try {
            $this->artisan('salon:install', ['--demo' => 'nails', '--email' => 'nails@example.test'])->assertSuccessful();
            $this->assertSame('nails', Salon::current()->theme);
            $this->assertDatabaseHas('services', ['name' => 'Műkörömépítés']);
            $this->get(route('home'))->assertOk()->assertSee('nails-hero.webp')->assertSee('Minta Nails');
            $this->artisan('salon:install', ['--demo' => 'barber', '--email' => 'barber@example.test'])->assertFailed();
            $this->assertSame('nails', Salon::current()->theme);
        } finally {
            putenv('SALON_ADMIN_PASSWORD');
            unset($_ENV['SALON_ADMIN_PASSWORD']);
        }
    }
}
