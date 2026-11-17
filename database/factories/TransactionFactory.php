<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $unitPrice = Money::normalize((string) fake()->randomFloat(3, 0.5, 10));
        $total = Money::mul($unitPrice, $quantity);
        $amountPaid = Money::add($total, Money::normalize((string) fake()->numberBetween(0, 10)));

        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $total,
            'amount_paid' => $amountPaid,
            'change_given' => Money::sub($amountPaid, $total),
            'created_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
