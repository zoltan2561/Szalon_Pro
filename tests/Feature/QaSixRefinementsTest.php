<?php

namespace Tests\Feature;

use App\Http\Controllers\PasswordResetController;
use App\Models\Salon;
use App\Models\User;
use App\Services\Branding;
use App\Services\OperationsHealth;
use App\Services\SendPasswordResetLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class QaSixRefinementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_button_foreground_meets_aa_throughout_the_grey_range_and_custom_accents(): void
    {
        $branding = app(Branding::class);
        $colors = ['#6d2734', '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#00ffff', '#ff00ff'];
        for ($level = 0; $level <= 255; $level++) {
            $colors[] = '#'.str_repeat(str_pad(dechex($level), 2, '0', STR_PAD_LEFT), 3);
        }
        foreach ($colors as $color) {
            $this->assertGreaterThanOrEqual(4.5, $branding->contrast($color, $branding->foreground($color)), $color);
        }
    }

    /** @return array<string, array{int, string}> */
    public static function waitingTimes(): array
    {
        return ['short' => [30, '30 másodperc'], 'one minute' => [60, '60 másodperc'], 'rounded up' => [61, '2 perc'], 'two minutes' => [120, '2 perc'], 'one hour' => [3600, '60 perc']];
    }

    #[DataProvider('waitingTimes')]
    public function test_rate_limit_uses_minutes_above_sixty_seconds(int $seconds, string $waiting): void
    {
        Route::get('/qa6-rate-limit', fn () => throw new TooManyRequestsHttpException($seconds));
        $this->get('/qa6-rate-limit')->assertStatus(429)->assertHeader('Retry-After', (string) $seconds)->assertSee('Próbáld újra '.$waiting.' múlva.')->assertSee('Vissza a foglalási űrlapra');
    }

    public function test_login_rate_limit_returns_to_login(): void
    {
        Salon::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.submit'), ['email' => 'missing@example.test', 'password' => 'private-password'])->assertRedirect();
        }
        $this->post(route('login.submit'), ['email' => 'missing@example.test', 'password' => 'private-password'])->assertStatus(429)->assertSee('Vissza a belépéshez')->assertSee('href="'.route('login').'"', false)->assertDontSee('Vissza a foglalási űrlapra');
    }

    public function test_empty_confirmation_has_only_the_required_message(): void
    {
        foreach ([[], ['password_confirmation' => '']] as $confirmation) {
            $this->post(route('password.update'), $confirmation + ['email' => 'owner@example.test', 'token' => str_repeat('a', 64), 'password' => 'new-private-password'])->assertSessionHasErrors(['password_confirmation' => 'Add meg újra az új jelszót.'])->assertSessionDoesntHaveErrors('password')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        }
    }

    public function test_doctor_warns_only_about_due_old_password_reset_jobs(): void
    {
        Salon::factory()->create();
        SendPasswordResetLink::dispatch('private-owner@example.test');
        foreach (['default', 'password-resets'] as $queue) {
            DB::table('jobs')->insert(['queue' => $queue, 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'created_at' => now()->subMinutes(10)->timestamp, 'available_at' => ($queue === 'default' ? now()->subMinutes(10) : now()->addMinutes(10))->timestamp]);
        }
        $health = app(OperationsHealth::class);
        $this->travel(4)->minutes();
        $this->assertArrayNotHasKey('password_reset_queue', $health->warnings());
        $this->travel(2)->minutes();
        $this->assertSame('1 jelszó-visszaállítási kérés több mint öt perce feldolgozásra vár. Ellenőrizd az időzítőt és a password-resets feldolgozót.', $health->warnings()['password_reset_queue']);
        $this->artisan('salon:doctor')->expectsOutputToContain('password_reset_queue:')->doesntExpectOutputToContain('private-owner@example.test')->assertFailed();
    }

    public function test_failed_reset_mail_is_visible_to_doctor_without_disclosing_private_data(): void
    {
        Salon::factory()->create();
        config(['mail.default' => 'array']);
        $user = User::factory()->create();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('private-provider-token'));
        Log::spy();
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHas('success', PasswordResetController::RequestedMessage);
        $job = Queue::connection('database')->pop('password-resets');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();
        $health = app(OperationsHealth::class);
        $this->assertArrayHasKey('password_reset_delivery', $health->warnings());
        $this->assertStringNotContainsString($user->email, implode(' ', $health->warnings()));
        $this->assertStringNotContainsString('private-provider-token', implode(' ', $health->warnings()));
        Log::shouldHaveReceived('warning')->once()->with('A jelszó-visszaállító levél küldése sikertelen. Ellenőrizd a levélküldő beállításait.');
        $this->artisan('salon:doctor')->expectsOutputToContain('password_reset_delivery:')->assertFailed();
        Notification::fake();
        (new SendPasswordResetLink('unknown@example.test'))->handle($health);
        $this->assertArrayHasKey('password_reset_delivery', $health->warnings());
        $this->travel(61)->minutes();
        $this->assertArrayNotHasKey('password_reset_delivery', $health->warnings());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }
}
