<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PublicContent;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantCustomizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_copy_uses_demo_defaults_and_customer_overrides_without_html(): void
    {
        $salon = Salon::factory()->make(['demo' => true, 'theme' => 'barber']);
        $content = app(PublicContent::class)->forSalon($salon);
        $this->assertSame('Vágás. Szakáll. Karakter.', $content['services_heading']);
        $this->assertSame('Hajvágás, szakáll és szabad időpont egy helyen.', $content['hero_note']);

        $salon->demo = false;
        $salon->setAttribute('content', ['services_heading' => 'Vanessa szolgáltatásai']);
        $content = app(PublicContent::class)->forSalon($salon);
        $this->assertSame('Vanessa szolgáltatásai', $content['services_heading']);
        $this->assertSame('Szolgáltatás, szakember és szabad időpont egy helyen.', $content['hero_note']);
        $this->assertStringNotContainsString('bemutató', $content['studio_text']);
    }

    public function test_custom_text_color_and_images_are_saved_only_for_the_current_customer(): void
    {
        Storage::fake('public');
        $demo = Salon::factory()->create(['demo' => true, 'theme' => 'salon']);
        Service::factory()->create(['name' => 'A demó szolgáltatása']);
        $tenant = Tenant::factory()->create(['slug' => 'vanessa', 'name' => 'Vanessa', 'domain' => 'vanessa.localhost', 'status' => 'active']);
        [$salon, $owner] = app(TenantContext::class)->run($tenant, function (): array {
            $salon = Salon::factory()->create(['name' => 'Vanessa Szalon', 'demo' => false, 'phone' => '+36301234567', 'privacy' => 'Saját adatkezelési tájékoztató.']);
            Service::factory()->create(['name' => 'Vanessa szolgáltatása']);

            return [$salon, User::factory()->create(['role' => 'owner'])];
        });
        $salon->refresh();
        $appearance = config('branding.presets.salon.appearance');
        $appearance['secondary'] = '#E5DDCC';
        $data = $salon->only(['name', 'tagline', 'about', 'email', 'phone', 'address', 'theme', 'font', 'terms', 'privacy', 'min_notice_minutes', 'horizon_days', 'cancel_notice_hours', 'reminder_hours', 'retention_days']) + [
            'accent' => '#224466', 'appearance' => $appearance, 'hours' => array_fill(1, 7, ['start' => '09:00', 'end' => '18:00']), 'content_rights' => 1, 'published' => 1,
            'content' => ['services_heading' => 'Vanessa saját árlistája', 'footer_credit' => 'Vanessa saját lábléce', 'contact_heading' => '<script>alert(1)</script>'],
            'hero_image' => UploadedFile::fake()->image('hero.png', 1600, 1000), 'logo' => UploadedFile::fake()->image('logo.png', 200, 200), 'gallery' => [UploadedFile::fake()->image('gallery.png', 800, 600)],
        ];

        $this->actingAs($owner)->post('http://vanessa.localhost/admin/beallitasok', $data)->assertRedirect()->assertSessionHasNoErrors();

        $saved = app(TenantContext::class)->run($tenant, fn (): Salon => Salon::current()->fresh());
        $this->assertSame('Vanessa saját árlistája', $saved->content['services_heading']);
        $this->assertSame('#224466', $saved->accent);
        $this->assertSame('#E5DDCC', $saved->appearance['secondary']);
        foreach ([$saved->hero_image, $saved->logo, ...$saved->gallery] as $path) {
            $this->assertStringStartsWith('tenants/'.$tenant->id.'/', $path);
            Storage::disk('public')->assertExists($path);
        }
        $this->get('http://vanessa.localhost/')->assertOk()->assertSee('Vanessa saját árlistája')->assertSee('Vanessa saját lábléce')->assertSee('Vanessa szolgáltatása')->assertSee('--accent:#224466', false)->assertSee('--secondary:#E5DDCC', false)->assertSee('storage/'.$saved->hero_image)->assertDontSee('A demó szolgáltatása')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $invalidData = array_diff_key($data, array_flip(['hero_image', 'logo', 'gallery']));
        $invalidData['appearance']['secondary'] = 'red;position:fixed';
        $this->post('http://vanessa.localhost/admin/beallitasok', $invalidData)->assertSessionHasErrors('appearance.secondary');
        $this->assertSame('#E5DDCC', app(TenantContext::class)->run($tenant, fn (): string => Salon::current()->appearance['secondary']));

        auth()->forgetGuards();
        $this->get(Tenant::findOrFail(1)->url().'/')->assertOk()->assertSee('A demó szolgáltatása')->assertDontSee('Vanessa saját árlistája')->assertDontSee('Vanessa saját lábléce')->assertDontSee('--accent:#224466', false);
        $this->assertNull($demo->fresh()->content);
        $this->assertNull($demo->fresh()->hero_image);
    }

    public function test_unknown_content_fields_and_overlong_text_are_rejected(): void
    {
        $rules = app(PublicContent::class)->rules();
        $this->assertTrue(validator(['content' => ['tenant_id' => '2']], $rules)->fails());
        $this->assertTrue(validator(['content' => ['services_heading' => str_repeat('x', 3001)]], $rules)->fails());
        $this->assertFalse(validator(['content' => ['services_heading' => 'Saját cím']], $rules)->fails());
    }
}
