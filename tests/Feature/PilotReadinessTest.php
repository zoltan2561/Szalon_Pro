<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PilotReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_preserves_submitted_opening_hours_after_failed_save_and_resubmission(): void
    {
        config(['salon.admin_password' => 'local-pilot-test-password']);
        $this->artisan('salon:install', ['--email' => 'owner@example.test'])->assertSuccessful();
        Service::factory()->create();
        $salon = Salon::current();
        $data = $this->settingsData($salon);
        $this->actingAs(User::first())->from(route('admin.settings'))
            ->post(route('admin.settings.save'), array_replace($data, ['name' => '']))
            ->assertSessionHasErrors('name');
        $this->assertSame([], $salon->fresh()->opening_hours);
        $this->get(route('admin.settings'))->assertOk()
            ->assertSee('name="hours[1][start]" value="08:30"', false)
            ->assertSee('name="hours[1][end]" value="17:30"', false)
            ->assertSee('name="hours[1][break_start]" value="12:00"', false)
            ->assertSee('name="hours[1][break_end]" value="12:45"', false)
            ->assertSee('name="hours[7][closed]" value="1" checked', false);
        $this->post(route('admin.settings.save'), $data)->assertSessionHasNoErrors();
        $this->assertSame([['08:30', '12:00'], ['12:45', '17:30']], $salon->fresh()->opening_hours[1]);
        $this->assertSame([], $salon->fresh()->opening_hours[7]);
    }

    public function test_failed_staff_save_restores_only_the_submitted_staff_schedule(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $staff = Staff::factory()->create();
        Staff::factory()->create();
        $hours = [1 => ['start' => '08:30', 'end' => '17:30', 'break_start' => '12:00', 'break_end' => '12:45'], 7 => ['closed' => 1]];
        $data = ['name' => '', 'title' => 'Fodrász', 'bio' => '', 'email' => '', 'hours' => $hours, 'active' => 1];
        $this->actingAs($owner)->from(route('admin.staff'))->post(route('admin.staff.save', $staff), $data)->assertSessionHasErrors('name');
        $html = $this->get(route('admin.staff'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'name="hours[1][start]" value="08:30"'));
        $this->assertStringContainsString('name="hours[1][break_end]" value="12:45"', $html);
        $this->assertSame([['09:00', '18:00']], $staff->fresh()->weekly_hours[1]);
        $this->post(route('admin.staff.save', $staff), array_replace($data, ['name' => $staff->name]))->assertSessionHasNoErrors();
        $this->assertSame([['08:30', '12:00'], ['12:45', '17:30']], $staff->fresh()->weekly_hours[1]);
    }

    /** @return array<string, array{string, string, string}> */
    public static function placeholderContent(): array
    {
        return [
            'demo privacy' => ['BEMUTATÓ TÁJÉKOZTATÓ – ÉLES HASZNÁLATRA NEM ALKALMAS.', '+36 30 123 4567', 'privacy'],
            'lowercase marker' => ['Ez éles használatra nem alkalmas.', '+36 30 123 4567', 'privacy'],
            'formatted placeholder phone' => ['Saját adatkezelési tájékoztató.', '+36 00 000 0000', 'phone'],
            'compact placeholder phone' => ['Saját adatkezelési tájékoztató.', '+36000000000', 'phone'],
            'all zero phone' => ['Saját adatkezelési tájékoztató.', '000000000', 'phone'],
        ];
    }

    #[DataProvider('placeholderContent')]
    public function test_real_salon_cannot_publish_placeholder_content(string $privacy, string $phone, string $field): void
    {
        $salon = Salon::factory()->create(['demo' => false, 'published' => false]);
        Service::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->post(route('admin.settings.save'), array_replace($this->settingsData($salon), ['privacy' => $privacy, 'phone' => $phone, 'published' => 1]))
            ->assertSessionHasErrors($field);
        $this->assertFalse($salon->fresh()->published);
    }

    public function test_fresh_install_rejects_its_demo_privacy_and_allows_corrected_publication(): void
    {
        config(['salon.admin_password' => 'local-pilot-test-password']);
        $this->artisan('salon:install', ['--email' => 'owner@example.test'])->assertSuccessful();
        Service::factory()->create();
        $salon = Salon::current();
        $data = $this->settingsData($salon);
        $this->actingAs(User::first())->post(route('admin.settings.save'), array_replace($data, ['privacy' => $salon->privacy, 'published' => 1]))->assertSessionHasErrors('privacy');
        $this->assertFalse($salon->fresh()->published);
        $this->post(route('admin.settings.save'), $data + ['published' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($salon->fresh()->published);
    }

    /** @return array<string, mixed> */
    private function settingsData(Salon $salon): array
    {
        $salon->refresh();

        return array_replace($salon->only(['name', 'tagline', 'about', 'email', 'phone', 'address', 'theme', 'accent', 'font', 'terms', 'privacy', 'min_notice_minutes', 'horizon_days', 'cancel_notice_hours', 'reminder_hours', 'retention_days']), [
            'phone' => '+36 30 123 4567', 'address' => 'Budapest, Teszt utca 1.', 'privacy' => 'Saját adatkezelési tájékoztató.', 'content_rights' => 1,
            'hours' => [1 => ['start' => '08:30', 'end' => '17:30', 'break_start' => '12:00', 'break_end' => '12:45'], 2 => ['start' => '09:00', 'end' => '18:00'], 7 => ['closed' => 1]],
        ]);
    }
}
