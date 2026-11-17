<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Data\ProductListQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function show(int $id): ProductResource
    {
        return new ProductResource($this->products->findOrFail($id));
    }

    public function purchase(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'amount_paid' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ]);

        $receipt = $this->purchases->purchase(
            $request->user(),
            $product->id,
            $validated['quantity'],
            (string) $validated['amount_paid'],
        );

        return response()->json($receipt->toArray(), 201);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:products,name'],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999.999', 'decimal:0,3'],
            'quantity_available' => ['required', 'integer', 'min:0'],
        ]);

        $product = $this->products->create($validated);

        return response()->json(new ProductResource($product), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:products,name,'.$product->id],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999.999', 'decimal:0,3'],
            'quantity_available' => ['required', 'integer', 'min:0'],
        ]);

        $product = $this->products->update($product, $validated);

        return response()->json(new ProductResource($product));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(null, 204);
    }
}
