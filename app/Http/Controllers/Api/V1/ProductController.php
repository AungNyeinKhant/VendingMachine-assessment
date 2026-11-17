<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Data\ProductListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * REST endpoints for other clients (mobile app, machine touchscreen).
 * Reuses the web FormRequests, repository and PurchaseService so both
 * channels validate and behave identically; only the response shape differs.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly PurchaseService $purchases,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->products->paginate(ProductListQuery::fromArray($request->query())));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    /**
     * Domain exceptions (stock, funds, unavailable) render themselves as JSON 409/422.
     */
    public function purchase(PurchaseRequest $request, Product $product): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $receipt = $this->purchases->purchase(
            $user,
            $product->id,
            $request->integer('quantity'),
            (string) $request->validated('amount_paid'),
        );

        return response()->json($receipt->toArray(), 201);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->productData());

        return response()->json(new ProductResource($product), 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->products->update($product, $request->productData());

        return response()->json(new ProductResource($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(null, 204);
    }
}
