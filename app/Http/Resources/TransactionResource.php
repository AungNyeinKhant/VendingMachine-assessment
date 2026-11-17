<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'total_price' => (string) $this->total_price,
            'amount_paid' => (string) $this->amount_paid,
            'change_given' => (string) $this->change_given,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
