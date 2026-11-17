<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    /**
     * The three products from the assignment brief.
     * Prices keep three decimals (Pepsi = 6.885 USD).
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
            ['name' => 'Coke', 'price' => '3.990', 'quantity_available' => 10],
            ['name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => 10],
            ['name' => 'Water', 'price' => '0.500', 'quantity_available' => 20],
        ];
    }
}
