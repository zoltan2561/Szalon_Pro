<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\Branding;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_switcher_links_to_three_separate_sites_without_changing_local_data(): void
    {
        $salon = Salon::factory()->create(['demo' => true, 'name' => 'Minta Szalon', 'theme' => 'salon']);
        $service = Service::factory()->create(['name' => 'Hajvágás']);
        Staff::factory()->create(['title' => null]);
        config()->set('salon.demo_urls', ['salon' => 'https://demo.test/hair', 'barber' => 'https://demo.test/barber', 'nails' => 'https://demo.test/nails']);

        $this->get(route('home'))->assertOk()
            ->assertSee('class="public premium salon', false)
            ->assertSee('Hajvágás')
            ->assertSee('A hajad, a te elképzelésed.')
            ->assertSee('href="https://demo.test/hair"', false)
            ->assertSee('href="https://demo.test/barber"', false)
            ->assertSee('href="https://demo.test/nails"', false)
            ->assertSeeInOrder(['id="rolunk"', 'id="szolgaltatasok"', 'class="team-section', 'class="journey-section'], false);
        $this->get(route('home', ['stilus' => 'barber']))->assertRedirect('https://demo.test/barber');
        $this->get(route('booking.form', ['service_id' => $service->id]))->assertOk()
            ->assertSee('class="public premium salon', false)
            ->assertSee('Hajvágás')
            ->assertSee('Találd meg a fodrász időpontod.');
        $this->assertSame('salon', $salon->fresh()->theme);

        config()->set('salon.demo_urls.nails', 'https://demo.test/barber');
        $this->get(route('home'))->assertOk()->assertDontSee('Szakági demó kiválasztása');
    }

    public function test_demo_sales_contact_is_shown_only_when_it_is_valid_and_configured(): void
    {
        Salon::factory()->create(['demo' => true]);
        Service::factory()->create();

        $this->get(route('home'))->assertOk()->assertDontSee('Saját szalont szeretnék');
        config()->set('salon.demo_sales_contact', 'hello@example.test');
        $this->get(route('home'))->assertOk()->assertSee('href="mailto:hello@example.test"', false);
        config()->set('salon.demo_sales_contact', 'javascript:alert(1)');
        $this->get(route('home'))->assertOk()->assertDontSee('Saját szalont szeretnék');
        config()->set('salon.demo_sales_contact', 'https://example.test/kapcsolat');
        $this->get(route('home'))->assertOk()->assertSee('href="https://example.test/kapcsolat"', false);
    }

    public function test_demo_profiles_have_distinct_text_and_section_order(): void
    {
        $salon = Salon::factory()->create(['demo' => true]);
        $service = Service::factory()->create();
        Staff::factory()->create();

        foreach ([
            'salon' => ['service' => 'Női hajvágás', 'heading' => 'A hajad, a te elképzelésed.', 'order' => ['id="rolunk"', 'id="szolgaltatasok"', 'class="team-section', 'class="journey-section']],
            'barber' => ['service' => 'Szakálligazítás', 'heading' => 'Vágás. Szakáll. Karakter.', 'order' => ['id="szolgaltatasok"', 'class="team-section', 'id="rolunk"', 'class="journey-section']],
            'nails' => ['service' => 'Műkörömépítés', 'heading' => 'Kezek, amelyekre jó ránézni.', 'order' => ['id="rolunk"', 'id="szolgaltatasok"', 'class="journey-section', 'class="team-section']],
        ] as $theme => $profile) {
            $salon->update(['theme' => $theme, 'appearance' => config('branding.presets.'.$theme.'.appearance')]);
            $service->update(['name' => $profile['service']]);
            $this->get(route('home'))->assertOk()
                ->assertSee('class="public premium '.$theme, false)
                ->assertSee($profile['service'])
                ->assertSee($profile['heading'])
                ->assertSee(config('branding.presets.'.$theme.'.image').'-studio.webp')
                ->assertSeeInOrder($profile['order'], false);
            $this->get(route('booking.form'))->assertOk()
                ->assertSee(config('branding.presets.'.$theme.'.demo_copy.booking_heading'));
        }
    }

    public function test_demo_switcher_is_hidden_on_customer_sites(): void
    {
        $salon = Salon::factory()->create(['demo' => false, 'theme' => 'salon']);
        Service::factory()->create();
        config()->set('salon.demo_urls', ['salon' => 'https://demo.test/hair', 'barber' => 'https://demo.test/barber', 'nails' => 'https://demo.test/nails']);

        $this->get(route('home', ['stilus' => 'barber']))->assertOk()
            ->assertSee('class="public premium salon', false)
            ->assertDontSee('Szakági demó kiválasztása');
        $this->assertSame('salon', $salon->fresh()->theme);
    }

    public function test_owner_sees_saved_branding_after_editing_or_applying_a_preset(): void
    {
        $salon = Salon::factory()->create(['demo' => true, 'theme' => 'salon']);
        Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);

        $appearance = config('branding.presets.salon.appearance');
        $appearance['eyebrow'] = 'SAJÁT SZALONFELIRAT';
        $appearance['cta_label'] = 'Saját foglalási gomb';
        $this->actingAs($owner)->post(route('admin.settings.save'), $this->settingsData($salon, $appearance))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()
            ->assertSee('class="public premium salon', false)
            ->assertSee('SAJÁT SZALONFELIRAT')
            ->assertSee('Saját foglalási gomb');

        $this->post(route('admin.branding.preset'), ['preset' => 'barber'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()
            ->assertSee('class="public premium barber', false)
            ->assertSee(config('branding.presets.barber.appearance.eyebrow'));
    }

    public function test_booking_opens_on_an_available_day_after_closed_weekend(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-17 16:00', 'Europe/Budapest'));
        $hours = [1 => [['09:00', '18:00']], 2 => [['09:00', '18:00']], 3 => [['09:00', '18:00']], 4 => [['09:00', '18:00']], 5 => [['09:00', '18:00']], 6 => [], 7 => []];
        Salon::factory()->create(['opening_hours' => $hours]);
        Service::factory()->create();

        $this->get(route('booking.form'))->assertOk()
            ->assertSee('value="2026-10-19"', false)
            ->assertSee('2026. október 19., hétfő')
            ->assertDontSee('UTC+');
        $this->get(route('booking.form', ['date' => '']))->assertOk()
            ->assertSee('value="2026-10-19"', false);
    }

    private function settingsData(Salon $salon, array $appearance): array
    {
        $salon->refresh();

        return $salon->only(['name', 'tagline', 'about', 'email', 'phone', 'address', 'theme', 'accent', 'font', 'terms', 'privacy', 'min_notice_minutes', 'horizon_days', 'cancel_notice_hours', 'reminder_hours', 'retention_days']) + ['hours' => array_fill(1, 7, ['start' => '09:00', 'end' => '18:00']), 'content_rights' => 1, 'appearance' => $appearance, 'current_password' => '', 'new_password' => ''];
    }

    public function test_past_booking_date_has_an_explanation_and_no_bookable_times(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00', 'Europe/Budapest'));
        Salon::factory()->create();
        Service::factory()->create();
        $this->get(route('booking.form', ['date' => '2026-10-19']))->assertOk()
            ->assertSee('Ez a nap már elmúlt. Válassz mai vagy későbbi időpontot.')
            ->assertSee('Következő szabad nap keresése')->assertDontSee('type="radio"', false);
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
            $this->assertSame(config('branding.presets.'.$key.'.appearance.layout'), $salon->appearance['layout']);
            $this->assertSame('Saját márkanév', $salon->name);
            $this->assertSame('branding/own.webp', $salon->hero_image);
            $this->assertSame('branding/logo.png', $salon->logo);
            $this->assertSame($service->name, $service->fresh()->name);
            $this->get(route('home'))->assertOk()->assertSee('storage/branding/own.webp')->assertSee('Saját márkanév')->assertSee('layout-'.$salon->appearance['layout']);
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
        $this->get(route('home'))->assertSee('hero-without-image')->assertDontSee('hair-hero.webp');
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

    public function test_legacy_settings_password_change_revokes_remember_token_and_logs_out(): void
    {
        $salon = Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'previous-password-1']);
        $owner->forceFill(['remember_token' => 'old-token'])->save();
        $data = array_replace($this->settingsData($salon, config('branding.presets.salon.appearance')), ['current_password' => 'previous-password-1', 'new_password' => 'replacement-password-2']);

        $this->actingAs($owner)->post(route('admin.settings.save'), $data)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('replacement-password-2', $owner->fresh()->password));
        $this->assertNotSame('old-token', $owner->fresh()->remember_token);
        $this->assertGuest();
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
        $originalPassword = config('salon.admin_password');
        config(['salon.admin_password' => 'temporary-demo-password']);
        try {
            $this->artisan('salon:install', ['--demo' => 'nails', '--email' => 'nails@example.test'])->assertSuccessful();
            $this->assertSame('nails', Salon::current()->theme);
            $this->assertDatabaseHas('services', ['name' => 'Műkörömépítés']);
            $this->assertDatabaseHas('staff', ['name' => 'Nagy Lili']);
            $this->assertStringNotContainsString('telefonon', Salon::current()->terms);
            $this->get(route('home'))->assertOk()->assertSee('nails-hero.webp')->assertSee('Minta Nails')->assertSee('Gondos kézápolás és választott szín.');
            $this->artisan('salon:install', ['--demo' => 'barber', '--email' => 'barber@example.test'])->assertFailed();
            $this->assertSame('nails', Salon::current()->theme);
        } finally {
            config(['salon.admin_password' => $originalPassword]);
        }
    }

    public function test_barber_demo_installs_its_own_catalog_and_team(): void
    {
        $originalPassword = config('salon.admin_password');
        config(['salon.admin_password' => 'temporary-demo-password']);
        try {
            $this->artisan('salon:install', ['--demo' => 'barber', '--email' => 'barber@example.test'])->assertSuccessful();
            $this->assertSame('barber', Salon::current()->theme);
            $this->assertDatabaseHas('services', ['name' => 'Szakálligazítás']);
            $this->assertDatabaseMissing('services', ['name' => 'Műkörömépítés']);
            $this->assertDatabaseHas('staff', ['name' => 'Kovács Bence']);
            $this->get(route('home'))->assertOk()->assertSee('Vágás. Szakáll. Karakter.')->assertSee('Tiszta kontúr és ápolt forma.');
        } finally {
            config(['salon.admin_password' => $originalPassword]);
        }
    }

    public function test_hairdresser_demo_installs_its_own_catalog_and_team(): void
    {
        $originalPassword = config('salon.admin_password');
        config(['salon.admin_password' => 'temporary-demo-password']);
        try {
            $this->artisan('salon:install', ['--demo' => 'salon', '--email' => 'salon@example.test'])->assertSuccessful();
            $this->assertSame('salon', Salon::current()->theme);
            $this->assertDatabaseHas('services', ['name' => 'Alkalmi frizura']);
            $this->assertDatabaseMissing('services', ['name' => 'Szakálligazítás']);
            $this->assertDatabaseHas('staff', ['name' => 'Hajnal Luca']);
            $this->get(route('home'))->assertOk()->assertSee('A hajad, a te elképzelésed.')->assertSee('Formázás különleges alkalmakra.');
        } finally {
            config(['salon.admin_password' => $originalPassword]);
        }
    }
}
