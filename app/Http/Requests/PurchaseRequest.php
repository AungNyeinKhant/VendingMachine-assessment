<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.purchase') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            // Upper bound keeps amount_paid / change_given inside DECIMAL(12,3); a machine takes at most $10,000.
            'amount_paid' => ['required', 'numeric', 'gt:0', 'max:10000', 'decimal:0,3'],
        ];
    }

    /**
     * Examples for the API docs (Scribe).
     *
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'quantity' => ['description' => 'How many to buy (1–1000, at most the stock).', 'example' => 3],
            'amount_paid' => ['description' => 'Money inserted in USD, up to 3 decimals (max 10000).', 'example' => '25'],
        ];
    }
}
