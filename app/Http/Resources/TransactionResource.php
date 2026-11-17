<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transaction resource that works with both Eloquent models and raw PDO arrays.
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        $createdAt = $resource['created_at'] ?? $resource->created_at ?? null;
        $createdAtString = '';
        if ($createdAt instanceof \DateTimeInterface) {
            $createdAtString = $createdAt->toIso8601String();
        } elseif (is_string($createdAt)) {
            $createdAtString = $createdAt;
        }

        return [
            'id' => $resource['id'] ?? $resource->id ?? 0,
            'user_id' => $resource['user_id'] ?? $resource->user_id ?? 0,
            'product_id' => $resource['product_id'] ?? $resource->product_id ?? 0,
            'quantity' => $resource['quantity'] ?? $resource->quantity ?? 0,
            'unit_price' => (string) ($resource['unit_price'] ?? $resource->unit_price ?? '0'),
            'total_price' => (string) ($resource['total_price'] ?? $resource->total_price ?? '0'),
            'amount_paid' => (string) ($resource['amount_paid'] ?? $resource->amount_paid ?? '0'),
            'change_given' => (string) ($resource['change_given'] ?? $resource->change_given ?? '0'),
            'created_at' => $createdAtString,
        ];
    }
}
