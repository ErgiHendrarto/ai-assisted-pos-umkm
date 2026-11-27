<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_access_all_navigation_routes_and_sees_admin_controls(): void
    {
        $admin = User::factory()->administrator()->create([
            'name' => 'Admin UMKM',
        ]);

        $dashboard = $this->actingAs($admin)->get(route('dashboard'));
        $dashboard->assertOk()
            ->assertSee('Admin UMKM')
            ->assertSee('Administrator')
            ->assertSee(route('products.index'), false)
            ->assertSee(route('categories.index'), false)
            ->assertSee(route('users.index'), false)
            ->assertSee('Manage Products')
            ->assertSee('View Transactions')
            ->assertSee('Manage Users');

        $this->actingAs($admin)->get(route('pos.index'))->assertOk();
        $this->actingAs($admin)->get(route('products.index'))->assertOk();
        $this->actingAs($admin)->get(route('categories.index'))->assertOk();
        $this->actingAs($admin)->get(route('transactions.index'))->assertOk();
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->actingAs($admin)->get(route('settings.index'))->assertOk();
    }

    public function test_cashier_can_access_cashier_routes_and_does_not_see_admin_navigation(): void
    {
        $cashier = User::factory()->cashier()->create([
            'name' => 'Cashier One',
        ]);

        $dashboard = $this->actingAs($cashier)->get(route('dashboard'));
        $dashboard->assertOk()
            ->assertSee('Cashier One')
            ->assertSee('Cashier')
            ->assertSee(route('pos.index'), false)
            ->assertSee(route('transactions.index'), false)
            ->assertSee(route('settings.index'), false)
            ->assertDontSee(route('products.index'), false)
            ->assertDontSee(route('categories.index'), false)
            ->assertDontSee(route('users.index'), false)
            ->assertSee('Start New Transaction')
            ->assertSee('View Transaction History');

        $this->actingAs($cashier)->get(route('pos.index'))->assertOk();
        $this->actingAs($cashier)->get(route('transactions.index'))->assertOk();
        $this->actingAs($cashier)->get(route('settings.index'))->assertOk();
    }

    public function test_cashier_cannot_access_administrator_only_pages(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get(route('products.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('categories.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('users.index'))->assertForbidden();
    }
}
