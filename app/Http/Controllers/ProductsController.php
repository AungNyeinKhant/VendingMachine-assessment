<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ProductListQuery;
use App\Exceptions\Domain\DomainException;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;

class ProductsController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly PurchaseService $purchases,
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
        $product = $this->products->create($request->productData());

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
        $product = $this->products->update($product, $request->productData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('products.show', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('products.index');
    }

    // Task 11: purchase actions use attribute routing (the .NET [HttpGet]-style
    // idea). Registered automatically by spatie/laravel-route-attributes.
    #[Get('/products/{product:slug}/purchase', name: 'products.purchase', middleware: ['web', 'auth', 'permission:products.purchase'])]
    public function showPurchase(Product $product): Response
    {
        return Inertia::render('products/purchase', [
            'product' => $product,
        ]);
    }

    #[Post('/products/{product:slug}/purchase', name: 'products.purchase.store', middleware: ['web', 'auth', 'permission:products.purchase'])]
    public function purchase(PurchaseRequest $request, Product $product): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $receipt = $this->purchases->purchase(
                $user,
                $product->id,
                $request->integer('quantity'),
                (string) $request->validated('amount_paid'),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$e->errorKey() => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase completed.')]);
        // Inertia flash (not ->with()): the page reads it from usePage().flash.receipt.
        Inertia::flash('receipt', $receipt->toArray());

        return to_route('products.purchase', $product);
    }
}
