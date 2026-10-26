<?php

namespace Tests\Feature;

use App\Http\Controllers\PasswordResetController;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Notifications\PasswordResetLink;
use App\Services\OperationsHealth;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Salon::factory()->create(['name' => 'Rózsa & Fésű']);
        config(['mail.default' => 'array']);
    }

    public function test_existing_and_unknown_addresses_receive_the_same_response(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'Owner@example.test']);
        $known = $this->post(route('password.email'), ['email' => 'OWNER@example.test'])->assertRedirect(route('password.request'))->assertSessionHas('success', PasswordResetController::RequestedMessage);
        Notification::assertNothingSent();
        $this->processResetRequests();
        Notification::assertSentTo($user, PasswordResetLink::class, function (PasswordResetLink $notification) use ($user): bool {
            $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
            $this->assertNotSame($notification->token, $stored);
            $this->assertTrue(Hash::check($notification->token, $stored));

            return true;
        });
        $this->post(route('password.email'), ['email' => 'missing@example.test'])->assertStatus($known->status())->assertSessionHas('success', PasswordResetController::RequestedMessage);
        $this->processResetRequests();
        Notification::assertCount(1);
        $this->get(route('login'))->assertOk()->assertSee('Elfelejtetted a jelszavad?');
        $this->get(route('password.request'))->assertOk()->assertSee('Visszaállító hivatkozást kérek');
    }

    public function test_account_lookup_token_and_notification_are_all_queued_outside_the_request_worker(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'Owner@example.test']);
        $kernel = app(Kernel::class);
        $responses = [];
        foreach (['OWNER@example.test', 'missing@example.test'] as $email) {
            $queries = [];
            DB::listen(function ($query) use (&$queries): void {
                $queries[] = $query->sql;
            });
            $request = Request::create(route('password.email'), 'POST', ['email' => $email]);
            $response = $kernel->handle($request);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame(route('password.request'), $response->headers->get('Location'));
            $this->assertSame(PasswordResetController::RequestedMessage, $request->session()->get('success'));
            $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains(strtolower($sql), 'lower(email)'))));
            if ($email === 'OWNER@example.test') {
                Notification::assertNothingSent();
                $this->assertDatabaseCount('password_reset_tokens', 0);
            }
            $responses[] = $response->getContent();
            $kernel->terminate($request, $response);
            $this->assertDatabaseCount('jobs', 1);
            $payload = DB::table('jobs')->value('payload');
            $this->assertStringNotContainsString($email, $payload);
            if ($email === 'OWNER@example.test') {
                Notification::assertNothingSent();
                $this->assertDatabaseCount('password_reset_tokens', 0);
            }
            $this->processResetRequests();
        }
        $this->assertSame($responses[0], $responses[1]);
        Notification::assertSentToTimes($user, PasswordResetLink::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        $this->processResetRequests();
        Notification::assertCount(1);
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['owner' => ['owner'], 'staff' => ['staff']];
    }

    #[DataProvider('roles')]
    public function test_reset_changes_password_once_and_revokes_only_target_sessions(string $role): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => $role, 'staff_id' => $role === 'staff' ? Staff::factory()->create()->id : null, 'remember_token' => 'old-remember-token']);
        $other = User::factory()->create();
        foreach ([$user, $other] as $account) {
            DB::table('sessions')->insert(['id' => 'session-'.$account->id, 'user_id' => $account->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        }
        $token = Password::broker()->createToken($user);
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Adj meg új jelszót.')->assertHeader('Cache-Control', 'no-store, private');
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'new-private-password', 'password_confirmation' => 'new-private-password'];
        $this->post(route('password.update'), $data)->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
        Notification::assertSentToTimes($user, PasswordChanged::class, 1);
        $this->post(route('password.update'), array_replace($data, ['password' => 'different-private-password', 'password_confirmation' => 'different-private-password']))->assertRedirect(route('password.request'))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Ez a hivatkozás már nem használható.')->assertSee('Új visszaállító hivatkozást kérek');
    }

    public function test_expired_token_cannot_change_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $oldHash = $user->password;
        $token = Password::broker()->createToken($user);
        $this->travel(61)->minutes();
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Ez a hivatkozás már nem használható.');
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => 'new-private-password', 'password_confirmation' => 'new-private-password'])->assertRedirect(route('password.request'))->assertSessionHasErrors('email');
        $this->assertSame($oldHash, $user->fresh()->password);
        Notification::assertNothingSent();
    }

    public function test_confirmation_and_password_length_are_enforced_without_flashing_secrets(): void
    {
        $user = User::factory()->create();
        $oldHash = $user->password;
        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);
        foreach ([['short', 'short'], ['new-private-password', 'does-not-match']] as [$password, $confirmation]) {
            $this->from($url)->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $confirmation])->assertRedirect($url)->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation')->assertSessionMissing('_old_input.token');
        }
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
    }

    public function test_link_requests_are_limited_per_ip_without_revealing_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'missing-'.$attempt.'@example.test'])->assertRedirect()->assertSessionHas('success', PasswordResetController::RequestedMessage);
        }
        $limited = $this->post(route('password.email'), ['email' => $user->email])->assertStatus(429)->assertSee('Túl sok kérés érkezett')->assertSee('Vissza a jelszókérő űrlapra');
        $this->assertGreaterThan(0, (int) $limited->headers->get('Retry-After'));
        $limited->assertSee('Próbáld újra '.$limited->headers->get('Retry-After').' másodperc múlva.');
        Notification::assertNothingSent();
    }

    public function test_email_limit_applies_across_ip_addresses_and_email_case(): void
    {
        Notification::fake();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])->post(route('password.email'), ['email' => 'absent@example.test'])->assertRedirect();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99'])->post(route('password.email'), ['email' => 'ABSENT@example.test'])->assertStatus(429)->assertSee('Túl sok kérés érkezett');
    }

    public function test_old_non_database_session_is_rejected_after_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'owner', 'password' => 'old-private-password']);
        $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'old-private-password'])->assertRedirect();
        $this->get(route('admin.today'))->assertOk();
        $oldSessionHash = session()->get('password_hash_web');
        $this->assertNotEmpty($oldSessionHash);
        Auth::logout();
        $token = Password::broker()->createToken($user);
        $this->post(route('password.update'), ['email' => $user->email, 'token' => $token, 'password' => 'new-private-password', 'password_confirmation' => 'new-private-password'])->assertRedirect(route('login'));
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldSessionHash])->get(route('admin.today'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $oldSessionHash])->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reset_email_has_salon_sender_plain_text_and_fixed_origin(): void
    {
        $user = User::factory()->create();
        config(['app.url' => 'https://trusted.example.test', 'mail.from.address' => 'verified@example.test']);
        $this->withHeaders(['Host' => 'untrusted.example.test'])->post(route('password.email'), ['email' => $user->email])->assertRedirect();
        $this->processResetRequests();
        $mail = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('Rózsa & Fésű', $mail->getFrom()[0]->getName());
        $this->assertSame('verified@example.test', $mail->getFrom()[0]->getAddress());
        $this->assertSame('salon@example.test', $mail->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('https://trusted.example.test/uj-jelszo/', $mail->getTextBody());
        $this->assertStringNotContainsString('untrusted.example.test', $mail->getTextBody());
        $this->assertStringContainsString('60 percig érvényes', $mail->getTextBody());
        $this->assertStringContainsString('Új jelszó beállítása', $mail->getHtmlBody());
    }

    public function test_unconfigured_or_logging_transport_sends_no_private_link_and_doctor_explains(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        config(['mail.default' => 'log']);
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect()->assertSessionHas('success', PasswordResetController::RequestedMessage);
        $this->processResetRequests();
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertArrayHasKey('password_reset', app(OperationsHealth::class)->warnings());
        $this->artisan('salon:doctor')->expectsOutputToContain('A webes jelszó-visszaállítás')->assertFailed();
    }

    private function processResetRequests(): void
    {
        while ($job = Queue::connection('database')->pop('password-resets')) {
            $job->fire();
            $job->delete();
        }
    }
}
