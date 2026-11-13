<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogListPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_list_separates_name_duration_price_and_status(): void
    {
        Salon::factory()->create();
        Service::factory()->create(['name' => 'Alkalmi frizura', 'category' => 'Hajformázás', 'duration_minutes' => 75, 'price' => 14500, 'price_kind' => 'from']);
        Service::factory()->create(['name' => 'Régi csomag', 'category' => 'Archívum', 'price' => 5000, 'active' => false]);

        $html = $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.services'))->assertOk()->getContent();

        $this->assertStringContainsString('<strong>Alkalmi frizura</strong><small>Hajformázás · 75 perc</small>', $html);
        $this->assertStringContainsString('14 500 Ft-tól', $html);
        $this->assertMatchesRegularExpression('/<span class="catalog-status is-active">Aktív<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/<span class="catalog-status is-archived">Archivált<\/span>/u', $html);
        $this->assertStringContainsString('<strong>+ Új szolgáltatás</strong>', $html);
        $this->assertStringNotContainsString('perc · 14 500 Ft · Aktív', $html);
    }

    public function test_staff_list_shows_the_specialty_and_weekly_hours_on_separate_lines(): void
    {
        Salon::factory()->create();
        Staff::factory()->create(['name' => 'Hajnal Luca', 'title' => 'Fodrász', 'weekly_hours' => [1 => [['09:00', '17:00']], 6 => [['09:00', '12:00']]]]);
        Staff::factory()->create(['name' => 'Szabadságos Szabó', 'title' => '', 'weekly_hours' => []]);

        $html = $this->actingAs(User::factory()->create(['role' => 'owner']))->get(route('admin.staff'))->assertOk()->getContent();

        $this->assertStringContainsString('<strong>Hajnal Luca</strong><small>Fodrász</small><small class="catalog-summary-hours">H: 09:00–17:00 · Szo: 09:00–12:00</small>', $html);
        $this->assertStringContainsString('<strong>Szabadságos Szabó</strong><small>Szakember</small><small class="catalog-summary-hours">Nincs heti munkaidő</small>', $html);
        $this->assertStringContainsString('<strong>+ Új szakember</strong>', $html);
    }
}
