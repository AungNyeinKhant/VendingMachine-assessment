<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_cannot_open_the_create_product_page(): void
    {
        $this->actingAs($this->user('user'))
            ->get(route('products.create'))
            ->assertForbidden();
    }

    public function test_purchase_page_uses_the_slug_url(): void
    {
        $product = Product::factory()->create(['name' => 'Pepsi', 'price' => '6.885', 'slug' => 'pepsi']);

        $this->assertStringEndsWith('/products/pepsi/purchase', route('products.purchase', $product));

        $this->actingAs($this->user('user'))
            ->get(route('products.purchase', $product))
            ->assertInertia(fn (Assert $page) => $page->component('products/purchase')->where('product.price', '6.885'));
    }

    public function test_purchase_decrements_stock_and_logs_a_price_snapshot(): void
    {
        $product = Product::factory()->create(['name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => 5]);
        $buyer = $this->user('user');

        $this->actingAs($buyer)
            ->post(route('products.purchase.store', $product), ['quantity' => 3, 'amount_paid' => '25'])
            ->assertRedirect(route('products.purchase', $product))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('receipt.total_price', '20.655')
            ->assertInertiaFlash('receipt.change_given', '4.345');

        $this->assertSame(2, $product->fresh()->quantity_available);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => '6.885',
            'total_price' => '20.655',
            'change_given' => '4.345',
        ]);
    }

    public function test_cannot_buy_more_than_available(): void
    {
        $product = Product::factory()->create(['quantity_available' => 1]);

        $this->actingAs($this->user('user'))
            ->post(route('products.purchase.store', $product), ['quantity' => 2, 'amount_paid' => '100'])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(1, $product->fresh()->quantity_available);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_purchase_with_insufficient_funds_shows_error(): void
    {
        $product = Product::factory()->create(['price' => '6.885', 'quantity_available' => 5]);

        $this->actingAs($this->user('user'))
            ->post(route('products.purchase.store', $product), ['quantity' => 1, 'amount_paid' => '1'])
            ->assertSessionHasErrors('amount_paid');
    }

    public function test_guest_redirected_to_login_on_purchase_page(): void
    {
        $product = Product::factory()->create();

        $this->get(route('products.purchase', $product))
            ->assertRedirect(route('login'));
    }

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }
}
