<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // Soft-deleted names stay reserved: the DB unique index still covers them.
                Rule::unique('products', 'name')
                    ->ignore($this->route('product')),
            ],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999.999', 'decimal:0,3'],
            'quantity_available' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * Validated input shaped for the repository: price normalized to 3 decimals.
     *
     * @return array{name: string, price: string, quantity_available: int}
     */
    public function productData(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'price' => Money::normalize($this->string('price')->trim()->value()),
            'quantity_available' => $this->integer('quantity_available'),
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
            'name' => ['description' => 'Unique product name.', 'example' => 'Iced Coffee'],
            'price' => ['description' => 'Price in USD, up to 3 decimals.', 'example' => '2.875'],
            'quantity_available' => ['description' => 'Units in the machine.', 'example' => 12],
        ];
    }
}
