<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantMigrationUpgradeTest extends TestCase
{
    public function test_upgrade_assigns_existing_data_to_demo_without_rewriting_any_values(): void
    {
        config(['database.connections.tenancy_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        $migration = 'database/migrations/2026_11_02_082417_add_shared_database_tenancy.php';
        $paths = array_values(array_filter(array_map(fn (string $path): string => 'database/migrations/'.basename($path), glob(database_path('migrations/*.php'))), fn (string $path): bool => $path !== $migration));
        try {
            Artisan::call('migrate', ['--database' => 'tenancy_upgrade', '--path' => $paths, '--force' => true, '--no-interaction' => true]);
            $db = DB::connection('tenancy_upgrade');
            $db->table('salons')->insert(['id' => 1, 'name' => 'Original demo', 'demo' => true, 'about' => 'Original content', 'gallery' => '["gallery/original.webp"]', 'appearance' => '{"paper":"#112233"}']);
            $db->table('users')->insert(['id' => 1, 'name' => 'Original owner', 'email' => 'owner@example.test', 'password' => 'unchanged-fixture-hash', 'role' => 'owner']);
            $db->table('staff')->insert(['id' => 1, 'name' => 'Original staff', 'weekly_hours' => '{"1":[["09:00","17:00"]]}']);
            $db->table('services')->insert(['id' => 1, 'name' => 'Original service', 'duration_minutes' => 45, 'price' => 7500]);
            $db->table('service_staff')->insert(['service_id' => 1, 'staff_id' => 1]);
            $db->table('guests')->insert(['id' => 1, 'name' => 'Original guest', 'email' => 'guest@example.test']);
            $before = [];
            foreach (config('tenancy.scoped_tables') as $table) {
                $before[$table] = $db->table($table)->get()->map(fn (object $row): array => (array) $row)->all();
            }
            Artisan::call('migrate', ['--database' => 'tenancy_upgrade', '--path' => [$migration], '--force' => true, '--no-interaction' => true]);
            $this->assertSame('demo', $db->table('tenants')->where('id', 1)->value('slug'));
            foreach ($before as $table => $rows) {
                $after = $db->table($table)->get()->map(fn (object $row): array => (array) $row)->all();
                $this->assertCount(count($rows), $after, $table);
                foreach ($rows as $index => $original) {
                    $this->assertSame(1, $after[$index]['tenant_id'], $table);
                    foreach ($original as $column => $value) {
                        $this->assertSame($value, $after[$index][$column], $table.'.'.$column);
                    }
                }
            }
        } finally {
            DB::purge('tenancy_upgrade');
        }

    }
}
