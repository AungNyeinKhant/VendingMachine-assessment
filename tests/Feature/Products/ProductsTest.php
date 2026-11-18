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

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $this->actingAs($this->user('admin'))
            ->get(route('products.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/index')
                ->has('products.data', 3));
    }

    public function test_user_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $this->actingAs($this->user('user'))
            ->get(route('products.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('products/index')
                ->has('products.data', 3));
    }

    public function test_sort_whitelist_ignores_invalid_sort(): void
    {
        Product::factory()->create(['name' => 'Coke', 'price' => '3.990']);
        Product::factory()->create(['name' => 'Pepsi', 'price' => '6.885']);

        // Try to sort by non-whitelisted column (password)
        $this->actingAs($this->user('admin'))
            ->get(route('products.index', ['sort' => 'password']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', 'name')); // falls back to default
    }

    public function test_admin_can_create_product(): void
    {
        $this->actingAs($this->user('admin'))
            ->post(route('products.store'), [
                'name' => 'Juice',
                'price' => '2.500',
                'quantity_available' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Juice',
            'price' => '2.500',
            'quantity_available' => 10,
        ]);
    }

    public function test_user_cannot_create_product(): void
    {
        $this->actingAs($this->user('user'))
            ->post(route('products.store'), [
                'name' => 'Juice',
                'price' => '2.500',
                'quantity_available' => 10,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_product(): void
    {
        $product = Product::factory()->create(['name' => 'Coke', 'price' => '3.990']);

        $this->actingAs($this->user('admin'))
            ->put(route('products.update', $product), [
                'name' => 'Coke Zero',
                'price' => '4.500',
                'quantity_available' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Coke Zero',
            'price' => '4.500',
        ]);
    }

    public function test_admin_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->user('admin'))
            ->delete(route('products.destroy', $product))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_product_price_must_be_positive_and_quantity_non_negative(): void
    {
        $this->actingAs($this->user('admin'))
            ->post(route('products.store'), ['name' => '', 'price' => '0', 'quantity_available' => -1])
            ->assertSessionHasErrors(['name', 'price', 'quantity_available']);
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
        $this->get(route('products.create'))->assertRedirect(route('login'));
        $this->post(route('products.store'), [])->assertRedirect(route('login'));
    }

    public function test_name_of_a_soft_deleted_product_is_still_taken(): void
    {
        Product::factory()->create(['name' => 'Coke'])->delete();

        $this->actingAs($this->user('admin'))
            ->post(route('products.store'), ['name' => 'Coke', 'price' => '3.99', 'quantity_available' => 1])
            ->assertSessionHasErrors('name');
    }

    public function test_slug_falls_back_and_avoids_reserved_segments(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('products.store'), ['name' => '!!!', 'price' => '1', 'quantity_available' => 1]);
        $this->actingAs($admin)->post(route('products.store'), ['name' => 'Create', 'price' => '1', 'quantity_available' => 1]);

        $this->assertDatabaseHas('products', ['name' => '!!!', 'slug' => 'product']);
        $this->assertDatabaseHas('products', ['name' => 'Create', 'slug' => 'create-2']);
    }

    public function test_array_search_param_is_ignored_instead_of_crashing(): void
    {
        $this->actingAs($this->user('user'))
            ->get(route('products.index', ['q' => ['x'], 'sort' => ['name']]))
            ->assertOk();
    }

    public function test_forbidden_page_uses_the_app_error_page_outside_local(): void
    {
        $this->app['env'] = 'production';

        $this->actingAs($this->user('user'))
            ->get(route('products.create'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
    }

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }
}
