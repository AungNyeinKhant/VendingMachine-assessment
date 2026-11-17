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
            $existing = Product::query()->firstWhere('name', $product['name']);

            if ($existing === null) {
                $product['slug'] = (new Product)->slugify($product['name']);
                Product::query()->create($product);
            } else {
                $existing->fill($product)->save();
            }
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
