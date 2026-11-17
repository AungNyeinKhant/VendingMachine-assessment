<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ProductListQuery;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function index(Request $request): Response
    {
        $query = ProductListQuery::fromArray($request->query());

        return Inertia::render('products/index', [
            'products' => $this->products->paginate($query),
            'filters' => $query->toArray(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = $this->products->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('products.show', $product);
    }

    public function show(Product $product): Response
    {
        return Inertia::render('products/show', [
            'product' => $product,
        ]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('products/edit', [
            'product' => $product,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product = $this->products->update($product, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('products.show', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('products.index');
    }
}
