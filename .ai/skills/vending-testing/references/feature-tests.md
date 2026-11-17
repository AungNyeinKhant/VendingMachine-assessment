# Feature test examples (database)

## Permissions and purchase (web)

```php
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
        $product = Product::factory()->create(['name' => 'Pepsi', 'price' => '6.885']);

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
            ->assertRedirect()
            ->assertSessionHasNoErrors();

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

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }
}
```

## API with JWT

```php
public function test_tampered_token_is_rejected(): void
{
    $token = app(JwtService::class)->issue($this->user('user'))['access_token'];
    $tampered = substr($token, 0, -1).(str_ends_with($token, 'a') ? 'b' : 'a');

    $this->withToken($tampered)->getJson('/api/v1/auth/me')->assertUnauthorized();
}

public function test_expired_token_is_rejected(): void
{
    $user = $this->user('user');
    $token = JWT::encode(
        ['iss' => config('app.url'), 'sub' => (string) $user->id, 'iat' => time() - 7200, 'exp' => time() - 3600],
        config('services.jwt.secret'),
        'HS256',
    );

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
}

public function test_user_cannot_create_products_via_api(): void
{
    $token = app(JwtService::class)->issue($this->user('user'))['access_token'];

    $this->withToken($token)
        ->postJson('/api/v1/products', ['name' => 'Juice', 'price' => '2.5', 'quantity_available' => 3])
        ->assertForbidden()
        ->assertJsonStructure(['message']);
}

public function test_api_returns_prices_as_strings(): void
{
    Product::factory()->create(['name' => 'Pepsi', 'price' => '6.885']);

    $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.price', '6.885');
}
```

Set `JWT_SECRET` in `phpunit.xml` (`<env name="JWT_SECRET" value="testing-secret-at-least-32-bytes-long!!"/>`) so tests never depend on a developer's `.env`.

## Validation (server-side half of Task 13)

```php
public function test_product_price_must_be_positive_and_quantity_non_negative(): void
{
    $this->actingAs($this->user('admin'))
        ->post(route('products.store'), ['name' => '', 'price' => '0', 'quantity_available' => -1])
        ->assertSessionHasErrors(['name', 'price', 'quantity_available']);
}
```
