<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_rendered_for_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('AI-Assisted POS')
            ->assertSee('Smart & Faster Transactions for UMKM')
            ->assertSee('Welcome Back');
    }

    public function test_user_can_login_with_valid_credentials_and_session_is_regenerated(): void
    {
        $user = User::factory()->administrator()->create([
            'email' => 'admin@pos.test',
            'password' => Hash::make('password'),
        ]);

        $this->get(route('login'));
        $initialSessionId = session()->getId();

        $response = $this->post(route('login.attempt'), [
            'email' => 'admin@pos.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($initialSessionId, session()->getId());
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->cashier()->create([
            'email' => 'cashier1@pos.test',
            'password' => Hash::make('password'),
        ]);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'cashier1@pos.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_requires_valid_email_and_password(): void
    {
        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->cashier()->inactive()->create([
            'email' => 'inactive@pos.test',
            'password' => Hash::make('password'),
        ]);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'email' => 'inactive@pos.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_login_page(): void
    {
        $user = User::factory()->cashier()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_user_can_logout_and_session_and_csrf_are_regenerated(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->get(route('dashboard'));
        $sessionIdBeforeLogout = session()->getId();
        $csrfBeforeLogout = csrf_token();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($sessionIdBeforeLogout, session()->getId());
        $this->assertNotSame($csrfBeforeLogout, csrf_token());
    }

    public function test_unauthenticated_users_cannot_access_protected_routes(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('pos.index'))->assertRedirect(route('login'));
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('categories.index'))->assertRedirect(route('login'));
        $this->get(route('transactions.index'))->assertRedirect(route('login'));
        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->get(route('settings.index'))->assertRedirect(route('login'));
    }
}
