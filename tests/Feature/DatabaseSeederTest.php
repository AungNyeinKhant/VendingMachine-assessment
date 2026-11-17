<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeder runs on every container start in production (Render restarts the
 * app when it wakes up), so it has to be idempotent and must never undo admin edits.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_keeps_admin_changes_and_does_not_duplicate(): void
    {
        $this->seed(DatabaseSeeder::class);

        $pepsi = Product::query()->where('name', 'Pepsi')->firstOrFail();
        $this->assertSame('6.885', $pepsi->price);

        $pepsi->update(['price' => '7.000', 'quantity_available' => 1]);
        Product::query()->where('name', 'Water')->firstOrFail()->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('7.000', $pepsi->fresh()->price);
        $this->assertSame(1, $pepsi->fresh()->quantity_available);
        $this->assertSame(1, Product::withTrashed()->where('name', 'Water')->count());
        $this->assertSame(1, Product::query()->where('name', 'Coke')->count());
        $this->assertTrue(User::query()->where('email', 'admin@vending.test')->firstOrFail()->can('products.manage'));
    }
}
