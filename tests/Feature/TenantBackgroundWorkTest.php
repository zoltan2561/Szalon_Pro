<?php

namespace Tests\Feature;

use App\Models\OutboxMessage;
use App\Models\Salon;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PasswordResetLink;
use App\Services\NotificationSender;
use App\Services\OperationsHealth;
use App\Services\SendPasswordResetLink;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TenantBackgroundWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_job_records_the_dispatching_tenant(): void
    {
        $job = new SendPasswordResetLink('owner@example.test');

        $this->assertSame(1, $job->tenantId);
    }

    public function test_scheduled_notifications_visit_active_tenants_and_restore_context(): void
    {
        $tenant = $this->newTenant('vanessa');
        $this->newTenant('paused', 'suspended');
        $visited = [];
        $this->mock(NotificationSender::class, function ($mock) use (&$visited): void {
            $mock->shouldReceive('sendDue')->twice()->andReturnUsing(function () use (&$visited): int {
                $visited[] = app(TenantContext::class)->id();

                return 0;
            });
            $mock->shouldReceive('failedCount')->twice()->andReturn(0);
        });

        $this->artisan('salon:notify')->assertSuccessful();

        $this->assertSame([1, $tenant->id], $visited);
        $this->assertSame(1, app(TenantContext::class)->id());
    }

    public function test_queued_password_reset_uses_its_tenant_and_canonical_domain(): void
    {
        Notification::fake();
        config(['mail.default' => 'array']);
        Salon::factory()->create();
        $demoUser = User::factory()->create(['email' => 'shared@example.test']);
        $tenant = $this->newTenant('vanessa');
        $context = app(TenantContext::class);
        [$user, $job] = $context->run($tenant, function (): array {
            Salon::factory()->create(['name' => 'Vanessa']);
            $user = User::factory()->create(['email' => 'shared@example.test']);

            return [$user, new SendPasswordResetLink($user->email)];
        });

        $job->handle(app(OperationsHealth::class));

        Notification::assertNotSentTo($demoUser, PasswordResetLink::class);
        Notification::assertSentTo($user, PasswordResetLink::class, function (PasswordResetLink $notification) use ($context, $tenant, $user): bool {
            return $context->run($tenant, function () use ($notification, $user, $tenant): bool {
                $message = $notification->toMail($user);
                $this->assertStringStartsWith($tenant->url().'/uj-jelszo/', $message->viewData['resetUrl']);
                $this->assertTrue(Password::broker()->tokenExists($user, $notification->token));

                return true;
            });
        });
        $this->assertSame(1, $context->id());
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->getEmailForPasswordReset()]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $demoUser->email]);
    }

    public function test_suspended_tenants_do_not_receive_queued_password_resets(): void
    {
        Notification::fake();
        $tenant = $this->newTenant('paused');
        $job = app(TenantContext::class)->run($tenant, function (): SendPasswordResetLink {
            Salon::factory()->create();
            $user = User::factory()->create();

            return new SendPasswordResetLink($user->email);
        });
        $tenant->update(['status' => 'suspended']);

        $job->handle(app(OperationsHealth::class));

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_health_counts_and_heartbeats_are_isolated(): void
    {
        Salon::factory()->create();
        $tenant = $this->newTenant('vanessa');
        $health = app(OperationsHealth::class);
        $demoKey = $health->heartbeatKey('email');
        $health->recordRun('email');
        OutboxMessage::factory()->create();
        DB::table('jobs')->insert(['queue' => 'password-resets', 'payload' => json_encode(['tenant_id' => 1]), 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(10)->timestamp, 'created_at' => now()->subMinutes(10)->timestamp]);

        app(TenantContext::class)->run($tenant, function () use ($health, $demoKey): void {
            Salon::factory()->create();
            $this->assertNotSame($demoKey, $health->heartbeatKey('email'));
            $this->assertNull($health->lastRun('email'));
            $this->assertSame(0, $health->dueCounts()['email']);
            $this->assertArrayNotHasKey('password_reset_queue', $health->warnings());
            $health->recordRun('email');
        });
        $this->assertNotNull($health->lastRun('email'));
        $this->assertSame(1, $health->dueCounts()['email']);
        $this->assertArrayHasKey('password_reset_queue', $health->warnings());
    }

    public function test_legacy_backup_and_restore_fail_closed_on_shared_installations(): void
    {
        Salon::factory()->create();
        $this->newTenant('vanessa');
        $path = storage_path('framework/tenant-backup-must-not-exist.backup');

        $this->artisan('salon:backup', ['--path' => $path])->assertFailed();
        $this->assertFileDoesNotExist($path);
        $this->artisan('salon:restore', ['path' => $path])->assertFailed();
    }

    private function newTenant(string $slug, string $status = 'active'): Tenant
    {
        return Tenant::factory()->create(['slug' => $slug, 'name' => ucfirst($slug), 'domain' => $slug.'.example.test', 'status' => $status]);
    }
}
