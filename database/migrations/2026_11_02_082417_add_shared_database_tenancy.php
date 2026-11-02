<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 120);
            $table->string('domain')->unique();
            $table->string('status', 20)->default('active');
            $table->json('data')->nullable();
            $table->timestamps();
        });
        DB::table('tenants')->insert(['id' => 1, 'slug' => 'demo', 'name' => 'Meglévő bemutató', 'domain' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach (config('tenancy.scoped_tables') as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('tenant_id')->default(1)->constrained('tenants')->restrictOnDelete();
                $table->index('tenant_id');
            });
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false);
        });
        Schema::table('salons', function (Blueprint $table): void {
            $table->json('content')->nullable();
        });
        foreach ($this->tenantUniqueColumns() as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropUnique([$column]);
                    $table->unique(['tenant_id', $column]);
                }
            });
        }
        Schema::create('tenant_impersonations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('tenants')->count() > 1) {
            throw new RuntimeException('Több ügyféllel a visszaállítás csak teljes mentésből végezhető el.');
        }
        Schema::dropIfExists('tenant_impersonations');
        foreach ($this->tenantUniqueColumns() as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropUnique(['tenant_id', $column]);
                    $table->unique($column);
                }
            });
        }
        Schema::table('salons', fn (Blueprint $table) => $table->dropColumn('content'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_super_admin'));
        foreach (array_reverse(config('tenancy.scoped_tables')) as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
        Schema::dropIfExists('tenants');
    }

    /** @return array<string, list<string>> */
    private function tenantUniqueColumns(): array
    {
        return ['users' => ['email'], 'bookings' => ['request_key'], 'google_calendar_connections' => ['calendar_id'], 'guest_passes' => ['issue_key'], 'loyalty_entries' => ['request_key'], 'gift_vouchers' => ['code', 'issue_key'], 'voucher_redemptions' => ['request_key']];
    }
};
