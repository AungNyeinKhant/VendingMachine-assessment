<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Services\JwtService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_public_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_public_can_view_product(): void
    {
        $product = Product::factory()->create(['name' => 'Pepsi', 'price' => '6.885']);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Pepsi')
            ->assertJsonPath('data.price', '6.885');
    }

    public function test_api_returns_prices_as_strings(): void
    {
        Product::factory()->create(['name' => 'Pepsi', 'price' => '6.885']);

        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.price', '6.885');
    }

    public function test_user_cannot_create_products_via_api(): void
    {
        $user = User::factory()->create()->assignRole('user');
        $token = app(JwtService::class)->issue($user)['access_token'];

        $response = $this->withToken($token)
            ->postJson('/api/v1/products', ['name' => 'Juice', 'price' => '2.5', 'quantity_available' => 3]);

        $response->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_admin_can_create_product_via_api(): void
    {
        $user = User::factory()->create()->assignRole('admin');
        $token = app(JwtService::class)->issue($user)['access_token'];

        $response = $this->withToken($token)
            ->postJson('/api/v1/products', ['name' => 'Juice', 'price' => '2.500', 'quantity_available' => 3]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Juice')
            ->assertJsonPath('data.price', '2.500');
    }

    public function test_admin_can_update_product_via_api(): void
    {
        $product = Product::factory()->create(['name' => 'Coke', 'price' => '3.990']);
        $user = User::factory()->create()->assignRole('admin');
        $token = app(JwtService::class)->issue($user)['access_token'];

        $response = $this->withToken($token)
            ->putJson("/api/v1/products/{$product->id}", ['name' => 'Coke Zero', 'price' => '4.500', 'quantity_available' => 5]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Coke Zero');
    }

    public function test_admin_can_delete_product_via_api(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create()->assignRole('admin');
        $token = app(JwtService::class)->issue($user)['access_token'];

        $response = $this->withToken($token)
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_purchase_requires_authentication(): void
    {
        $product = Product::factory()->create(['quantity_available' => 5]);

        $response = $this->postJson("/api/v1/products/{$product->id}/purchase", [
            'quantity' => 1,
            'amount_paid' => '10',
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_can_purchase_via_api(): void
    {
        $product = Product::factory()->create(['name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => 5]);
        $user = User::factory()->create()->assignRole('user');
        $token = app(JwtService::class)->issue($user)['access_token'];

        $response = $this->withToken($token)
            ->postJson("/api/v1/products/{$product->id}/purchase", ['quantity' => 3, 'amount_paid' => '25']);

        $response->assertCreated()
            ->assertJsonPath('product_name', 'Pepsi')
            ->assertJsonPath('total_price', '20.655')
            ->assertJsonPath('change_given', '4.345');

        $this->assertSame(2, $product->fresh()->quantity_available);
    }

    public function test_api_purchase_insufficient_stock_returns_409(): void
    {
        $product = Product::factory()->create(['quantity_available' => 1]);
        $user = User::factory()->create()->assignRole('user');
        $token = app(JwtService::class)->issue($user)['access_token'];

        $response = $this->withToken($token)
            ->postJson("/api/v1/products/{$product->id}/purchase", ['quantity' => 2, 'amount_paid' => '100']);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Only 1 left in stock.')
            ->assertJsonPath('errors.quantity.0', 'Only 1 left in stock.');
    }

    public function test_api_purchase_insufficient_funds_returns_422(): void
    {
        $product = Product::factory()->create(['price' => '6.885', 'quantity_available' => 5]);
        $user = User::factory()->create()->assignRole('user');
        $token = app(JwtService::class)->issue($user)['access_token'];

        $response = $this->withToken($token)
            ->postJson("/api/v1/products/{$product->id}/purchase", ['quantity' => 1, 'amount_paid' => '1']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.amount_paid.0', 'You need to insert at least 6.885 USD.');
    }

    public function test_purchase_accepts_form_encoded_body(): void
    {
        $product = Product::factory()->create(['price' => '6.885', 'quantity_available' => 5]);
        $token = $this->tokenFor('user');

        // Form fields arrive as strings ("2"); the controller must still pass an int to the service.
        $this->withToken($token)
            ->post("/api/v1/products/{$product->id}/purchase", ['quantity' => '2', 'amount_paid' => '20'])
            ->assertCreated()
            ->assertJsonPath('total_price', '13.770')
            ->assertJsonPath('change_given', '6.230');
    }

    public function test_domain_errors_are_json_even_without_accept_header(): void
    {
        $product = Product::factory()->create(['quantity_available' => 1]);
        $token = $this->tokenFor('user');

        $this->withToken($token)
            ->post("/api/v1/products/{$product->id}/purchase", ['quantity' => '5', 'amount_paid' => '100'])
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'errors' => ['quantity']]);
    }

    public function test_unknown_or_non_numeric_product_id_is_404(): void
    {
        $this->getJson('/api/v1/products/999999')->assertNotFound();
        $this->getJson('/api/v1/products/abc')->assertNotFound();
    }

    private function tokenFor(string $role): string
    {
        return app(JwtService::class)->issue(User::factory()->create()->assignRole($role))['access_token'];
    }
}
