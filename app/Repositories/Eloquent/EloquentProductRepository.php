<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Data\ProductListQuery;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function paginate(ProductListQuery $query): LengthAwarePaginator
    {
        return Product::query()
            ->when($query->search, fn ($q, string $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy($query->sort, $query->direction) // already whitelisted by the DTO
            ->orderBy('id') // stable order across pages
            ->paginate($query->perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): Product
    {
        return Product::query()->findOrFail($id);
    }

    public function findForUpdate(int $id): ?Product
    {
        return Product::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function create(array $attributes): Product
    {
        $attributes['slug'] = (new Product)->slugify($attributes['name']);

        return Product::query()->create($attributes);
    }

    public function update(Product $product, array $attributes): Product
    {
        if (isset($attributes['name']) && $attributes['name'] !== $product->name) {
            $attributes['slug'] = $product->slugify($attributes['name']);
        }

        $product->fill($attributes)->save();

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function decrementStock(Product $product, int $quantity): void
    {
        $product->decrement('quantity_available', $quantity);
    }
}
