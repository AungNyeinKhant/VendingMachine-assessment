<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PurchaseReceipt;
use App\Exceptions\Domain\InsufficientFundsException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Support\Money;
use Illuminate\Database\ConnectionInterface;

/**
 * One purchase = one DB transaction:
 * lock the product row, check stock and funds, decrement stock, write a
 * transaction log row with a price snapshot. Any domain failure rolls
 * everything back.
 */
class PurchaseService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ProductRepositoryInterface $products,
        private readonly TransactionRepositoryInterface $transactions,
    ) {}

    public function purchase(User $user, int $productId, int $quantity, string $amountPaid): PurchaseReceipt
    {
        return $this->db->transaction(function () use ($user, $productId, $quantity, $amountPaid): PurchaseReceipt {
            $product = $this->products->findForUpdate($productId)
                ?? throw new ProductUnavailableException;

            if ($product->quantity_available < $quantity) {
                throw new InsufficientStockException($product->quantity_available);
            }

            $total = Money::mul($product->price, $quantity);

            if (Money::lt($amountPaid, $total)) {
                throw new InsufficientFundsException($total);
            }

            $this->products->decrementStock($product, $quantity);

            $transactionId = $this->transactions->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price, // price snapshot — later price edits don't rewrite history
                'total_price' => $total,
                'amount_paid' => Money::normalize($amountPaid),
                'change_given' => Money::sub($amountPaid, $total),
            ]);

            return new PurchaseReceipt(
                $transactionId,
                $product->name,
                $quantity,
                $product->price,
                $total,
                Money::normalize($amountPaid),
                Money::sub($amountPaid, $total),
            );
        });
    }
}
