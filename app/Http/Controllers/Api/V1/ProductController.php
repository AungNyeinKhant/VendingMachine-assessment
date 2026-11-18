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

    /**
     * List products
     *
     * Paginated, sortable and searchable. Prices are decimal strings ("6.885").
     *
     * @group Products
     *
     * @unauthenticated
     *
     * @queryParam sort string Column: name, price, quantity_available or created_at. Example: price
     * @queryParam direction string asc or desc. Example: desc
     * @queryParam per_page integer 5, 10 or 25. Example: 10
     * @queryParam q string Search by name. Example: pep
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->products->paginate(ProductListQuery::fromArray($request->query())));
    }

    /**
     * Show a product
     *
     * @group Products
     *
     * @unauthenticated
     *
     * @urlParam product integer required The product ID. Example: 2
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    /**
     * Purchase a product
     *
     * The server reads the price from the database (locked row) — clients only send
     * the quantity and the money inserted. Domain errors: 409 not enough stock /
     * product unavailable, 422 not enough money.
     *
     * @group Purchases
     *
     * @authenticated
     *
     * @urlParam product integer required The product ID. Example: 2
     *
     * @response 201 {"transaction_id": 1, "product_name": "Pepsi", "quantity": 3, "unit_price": "6.885", "total_price": "20.655", "amount_paid": "25.000", "change_given": "4.345"}
     * @response 409 {"message": "Only 7 left in stock.", "errors": {"quantity": ["Only 7 left in stock."]}}
     * @response 422 {"message": "You need to insert at least 20.655 USD.", "errors": {"amount_paid": ["You need to insert at least 20.655 USD."]}}
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

    /**
     * Create a product
     *
     * Requires the `products.manage` permission (admin).
     *
     * @group Products
     *
     * @authenticated
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->productData());

        // ->response() keeps the {"data": ...} wrapper, same shape as index/show.
        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /**
     * Update a product
     *
     * Requires the `products.manage` permission (admin).
     *
     * @group Products
     *
     * @authenticated
     *
     * @urlParam product integer required The product ID. Example: 2
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->products->update($product, $request->productData());

        return (new ProductResource($product))->response();
    }

    /**
     * Delete a product
     *
     * Soft delete — purchase history keeps pointing at it. Requires `products.manage`.
     *
     * @group Products
     *
     * @authenticated
     *
     * @urlParam product integer required The product ID. Example: 14
     *
     * @response 204 scenario="Deleted"
     */
    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(null, 204);
    }
}
