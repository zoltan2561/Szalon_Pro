<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_has_separate_account_and_settings_navigation(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($owner)->get(route('admin.settings'))->assertOk()
            ->assertSee('href="#settings-business"', false)
            ->assertSee('href="#settings-publish"', false)
            ->assertSee('id="settings-business"', false)
            ->assertSee('id="settings-publish"', false)
            ->assertSee('A mentés az összes alábbi beállítást együtt rögzíti.')
            ->assertDontSee('name="new_password"', false);
        $this->get(route('admin.account'))->assertOk()->assertSee('Jelszó módosítása')->assertSee('name="current_password"', false);
    }

    public function test_password_requires_current_password_and_matching_confirmation(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'previous-password-1']);
        $this->actingAs($owner)->post(route('admin.account.password'), ['current_password' => 'incorrect-password', 'password' => 'replacement-password-2', 'password_confirmation' => 'replacement-password-2'])
            ->assertSessionHasErrors('current_password');
        $this->post(route('admin.account.password'), ['current_password' => 'previous-password-1', 'password' => 'replacement-password-2', 'password_confirmation' => 'different-password-3'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('previous-password-1', $owner->fresh()->password));
    }

    public function test_password_change_revokes_remember_token_and_logs_out(): void
    {
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'previous-password-1', 'remember_token' => 'old-token']);
        $this->actingAs($owner)->post(route('admin.account.password'), ['current_password' => 'previous-password-1', 'password' => 'replacement-password-2', 'password_confirmation' => 'replacement-password-2'])
            ->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('replacement-password-2', $owner->fresh()->password));
        $this->assertNotSame('old-token', $owner->fresh()->remember_token);
        $this->assertFalse(Auth::check());
        $this->get(route('admin.account'))->assertRedirect(route('login'));
    }

    public function test_staff_cannot_access_owner_account_settings(): void
    {
        Salon::factory()->create();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->get(route('admin.account'))->assertForbidden();
        $this->post(route('admin.account.password'), [])->assertForbidden();
    }

    public function test_password_change_revokes_database_sessions_for_only_the_current_user(): void
    {
        config(['session.driver' => 'database']);
        Salon::factory()->create();
        $owner = User::factory()->create(['role' => 'owner', 'password' => 'previous-password-1']);
        $other = User::factory()->create();
        foreach (['old-owner-session' => $owner->id, 'other-user-session' => $other->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp]);
        }

        $this->actingAs($owner)->post(route('admin.account.password'), ['current_password' => 'previous-password-1', 'password' => 'replacement-password-2', 'password_confirmation' => 'replacement-password-2'])
            ->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'old-owner-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);
    }
}
