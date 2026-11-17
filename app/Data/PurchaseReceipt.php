<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Everything shown on the purchase receipt. Money values are decimal
 * strings ("20.655") — never floats.
 */
final readonly class PurchaseReceipt
{
    public function __construct(
        public int $transactionId,
        public string $productName,
        public int $quantity,
        public string $unitPrice,
        public string $totalPrice,
        public string $amountPaid,
        public string $changeGiven,
    ) {}

    /**
     * @return array{transaction_id: int, product_name: string, quantity: int, unit_price: string, total_price: string, amount_paid: string, change_given: string}
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'product_name' => $this->productName,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'total_price' => $this->totalPrice,
            'amount_paid' => $this->amountPaid,
            'change_given' => $this->changeGiven,
        ];
    }
}
