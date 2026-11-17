<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('products', 'name')->withoutTrashed()],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999.999', 'decimal:0,3'],
            'quantity_available' => ['required', 'integer', 'min:0'],
        ];
    }
}
