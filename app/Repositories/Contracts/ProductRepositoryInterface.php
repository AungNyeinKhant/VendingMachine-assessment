<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Data\ProductListQuery;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    /**
     * Paginated product list with whitelisted sorting and optional search.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(ProductListQuery $query): LengthAwarePaginator;

    public function findOrFail(int $id): Product;

    /**
     * Must be called inside a DB transaction. Locks the row (SELECT ... FOR UPDATE)
     * so concurrent purchases of the last item cannot oversell.
     */
    public function findForUpdate(int $id): ?Product;

    /**
     * @param  array{name: string, price: string, quantity_available: int}  $attributes
     */
    public function create(array $attributes): Product;

    /**
     * @param  array{name?: string, price?: string, quantity_available?: int}  $attributes
     */
    public function update(Product $product, array $attributes): Product;

    public function delete(Product $product): void;

    public function decrementStock(Product $product, int $quantity): void;
}
