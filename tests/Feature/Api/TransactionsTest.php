<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\JwtService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_can_view_own_transactions(): void
    {
        $buyer = User::factory()->create()->assignRole('user');
        $other = User::factory()->create()->assignRole('user');
        $product = Product::factory()->create(['price' => '6.885', 'quantity_available' => 10]);

        Transaction::factory()->create(['user_id' => $buyer->id, 'product_id' => $product->id, 'unit_price' => '6.885', 'total_price' => '6.885', 'amount_paid' => '10', 'change_given' => '3.115']);
        Transaction::factory()->create(['user_id' => $other->id, 'product_id' => $product->id, 'unit_price' => '6.885', 'total_price' => '6.885', 'amount_paid' => '10', 'change_given' => '3.115']);

        $token = app(JwtService::class)->issue($buyer)['access_token'];

        $response = $this->withToken($token)->getJson('/api/v1/transactions');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $buyer->id);
    }

    public function test_admin_with_view_all_sees_all_transactions(): void
    {
        $buyer = User::factory()->create()->assignRole('user');
        $other = User::factory()->create()->assignRole('user');
        $admin = User::factory()->create()->assignRole('admin');
        $product = Product::factory()->create(['price' => '6.885', 'quantity_available' => 10]);

        Transaction::factory()->create(['user_id' => $buyer->id, 'product_id' => $product->id, 'unit_price' => '6.885', 'total_price' => '6.885', 'amount_paid' => '10', 'change_given' => '3.115']);
        Transaction::factory()->create(['user_id' => $other->id, 'product_id' => $product->id, 'unit_price' => '6.885', 'total_price' => '6.885', 'amount_paid' => '10', 'change_given' => '3.115']);

        $token = app(JwtService::class)->issue($admin)['access_token'];

        $response = $this->withToken($token)->getJson('/api/v1/transactions');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_guest_cannot_view_transactions(): void
    {
        $response = $this->getJson('/api/v1/transactions');

        $response->assertUnauthorized();
    }

    public function test_web_transactions_page_scopes_to_own(): void
    {
        $buyer = User::factory()->create()->assignRole('user');
        $other = User::factory()->create()->assignRole('user');
        $product = Product::factory()->create(['price' => '6.885', 'quantity_available' => 10]);

        Transaction::factory()->create(['user_id' => $buyer->id, 'product_id' => $product->id]);
        Transaction::factory()->create(['user_id' => $other->id, 'product_id' => $product->id]);

        $this->actingAs($buyer)
            ->get(route('transactions.index'))
            ->assertInertia(fn ($page) => $page->has('transactions.data', 1));
    }

    public function test_admin_web_transactions_page_shows_all(): void
    {
        $buyer = User::factory()->create()->assignRole('user');
        $other = User::factory()->create()->assignRole('user');
        $admin = User::factory()->create()->assignRole('admin');
        $product = Product::factory()->create(['price' => '6.885', 'quantity_available' => 10]);

        Transaction::factory()->create(['user_id' => $buyer->id, 'product_id' => $product->id]);
        Transaction::factory()->create(['user_id' => $other->id, 'product_id' => $product->id]);

        $this->actingAs($admin)
            ->get(route('transactions.index'))
            ->assertInertia(fn ($page) => $page->has('transactions.data', 2)->where('canViewAll', true));
    }
}
