<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoProductsSeeder extends Seeder
{
    /**
     * Extra snacks so pagination (per page 5/10/25) is visible in the demo.
     * Idempotent and insert-only (see run()).
     */
    public function run(): void
    {
        foreach ($this->catalog() as $product) {
            // Insert-only: runs on every deploy/restart (Render free tier restarts when
            // it wakes up), so it must never overwrite stock or prices an admin changed.
            // withTrashed(): a soft-deleted product keeps its unique name — don't recreate it.
            if (Product::withTrashed()->where('name', $product['name'])->exists()) {
                continue;
            }

            $product['slug'] = (new Product)->slugify($product['name']);
            Product::query()->create($product);
        }
    }

    /**
     * @return list<array{name: string, price: string, quantity_available: int}>
     */
    private function catalog(): array
    {
        return [
            ['name' => 'Chips', 'price' => '2.500', 'quantity_available' => 15],
            ['name' => 'Chocolate Bar', 'price' => '1.750', 'quantity_available' => 25],
            ['name' => 'Cookies', 'price' => '2.250', 'quantity_available' => 12],
            ['name' => 'Gum', 'price' => '0.750', 'quantity_available' => 30],
            ['name' => 'Juice Box', 'price' => '4.500', 'quantity_available' => 8],
            ['name' => 'Nuts', 'price' => '3.250', 'quantity_available' => 10],
            ['name' => 'Popcorn', 'price' => '2.000', 'quantity_available' => 14],
            ['name' => 'Pretzels', 'price' => '1.950', 'quantity_available' => 18],
            ['name' => 'Soda Can', 'price' => '3.500', 'quantity_available' => 0],
            ['name' => 'Tea Bottle', 'price' => '4.000', 'quantity_available' => 6],
        ];
    }
}
