<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Data\ProductListQuery;
use App\Data\PurchaseReceipt;
use App\Exceptions\Domain\InsufficientFundsException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Http\Controllers\ProductsController;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductsControllerTest extends TestCase
{
    private ProductRepositoryInterface&MockInterface $products;

    private PurchaseService&MockInterface $purchases;

    private ProductsController $controller;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = new User(['name' => 'Test']);
        $this->products = Mockery::mock(ProductRepositoryInterface::class);
        $this->purchases = Mockery::mock(PurchaseService::class);
        $this->controller = new ProductsController($this->products, $this->purchases);
    }

    public function test_index_calls_repository_with_parsed_query(): void
    {
        $this->products->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(fn (ProductListQuery $q) => $q->sort === 'name' && $q->direction === 'asc' && $q->perPage === 10))
            ->andReturn(new LengthAwarePaginator([], 0, 10));

        $request = Request::create('/products', 'GET', ['sort' => 'password', 'direction' => 'invalid', 'per_page' => 999]);
        $response = $this->controller->index($request);

        $this->assertInstanceOf(InertiaResponse::class, $response);

        $page = $this->inertiaPage($response);
        $this->assertSame('products/index', $page['component']);
        $this->assertSame('name', $page['props']['filters']['sort']);
        $this->assertSame(10, $page['props']['filters']['per_page']);
        $this->assertArrayHasKey('products', $page['props']);
    }

    public function test_store_creates_product_from_validated_data_and_redirects_to_it(): void
    {
        $product = $this->product();
        $data = ['name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => 2];
        $request = Mockery::mock(StoreProductRequest::class);
        $request->shouldReceive('productData')->andReturn($data);

        $this->products->shouldReceive('create')->once()->with($data)->andReturn($product);

        $response = $this->controller->store($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('products.show', $product), $response->getTargetUrl());
    }

    public function test_create_show_and_edit_render_the_right_pages(): void
    {
        $product = $this->product();

        $this->assertSame('products/create', $this->inertiaPage($this->controller->create())['component']);

        $show = $this->inertiaPage($this->controller->show($product));
        $this->assertSame('products/show', $show['component']);
        $this->assertSame('pepsi', $show['props']['product']['slug']);

        $edit = $this->inertiaPage($this->controller->edit($product));
        $this->assertSame('products/edit', $edit['component']);
        $this->assertSame('6.885', $edit['props']['product']['price']);
    }

    public function test_update_persists_validated_data_and_redirects(): void
    {
        $product = $this->product();
        $data = ['name' => 'Coke Updated', 'price' => '4.990', 'quantity_available' => 5];
        $request = Mockery::mock(UpdateProductRequest::class);
        $request->shouldReceive('productData')->andReturn($data);

        $this->products->shouldReceive('update')->once()->with($product, $data)->andReturn($product);

        $response = $this->controller->update($request, $product);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('products.show', $product), $response->getTargetUrl());
    }

    public function test_destroy_deletes_product_and_redirects(): void
    {
        $product = $this->product();

        $this->products->shouldReceive('delete')->once()->with($product);

        $response = $this->controller->destroy($product);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('products.index'), $response->getTargetUrl());
    }

    public function test_purchase_maps_insufficient_stock_to_quantity_error(): void
    {
        $product = $this->product();
        $request = $this->purchaseRequest(quantity: 5, amountPaid: '50');

        $this->purchases->shouldReceive('purchase')->once()->andThrow(new InsufficientStockException(2));

        try {
            $this->controller->purchase($request, $product);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }
    }

    public function test_purchase_maps_insufficient_funds_to_amount_paid_error(): void
    {
        $this->purchases->shouldReceive('purchase')->andThrow(new InsufficientFundsException('6.885'));

        $this->expectException(ValidationException::class);

        $this->controller->purchase($this->purchaseRequest(1, '1'), $this->product());
    }

    public function test_purchase_maps_product_unavailable_to_error(): void
    {
        $this->purchases->shouldReceive('purchase')->andThrow(new ProductUnavailableException);

        $this->expectException(ValidationException::class);

        $this->controller->purchase($this->purchaseRequest(1, '10'), $this->product());
    }

    public function test_purchase_success_redirects_with_receipt(): void
    {
        $product = $this->product();
        $request = $this->purchaseRequest(2, '20.000');
        $receipt = new PurchaseReceipt(1, 'Pepsi', 2, '6.885', '13.770', '20.000', '6.230');

        // The controller must pass the logged-in user, product id, quantity and money as given.
        $this->purchases->shouldReceive('purchase')
            ->once()
            ->with($this->buyer, 2, 2, '20.000')
            ->andReturn($receipt);

        $response = $this->controller->purchase($request, $product);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('products.purchase', $product), $response->getTargetUrl());
    }

    // --- helpers -------------------------------------------------------

    /**
     * Resolve an Inertia response into its page object (component + props).
     *
     * @return array{component: string, props: array<string, mixed>}
     */
    private function inertiaPage(InertiaResponse $response): array
    {
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_INERTIA' => 'true']);

        return $response->toResponse($request)->getData(true);
    }

    private function product(): Product
    {
        $product = new Product(['name' => 'Pepsi', 'slug' => 'pepsi', 'price' => '6.885', 'quantity_available' => 2]);
        $product->id = 2;

        return $product;
    }

    private function purchaseRequest(int $quantity, string $amountPaid): PurchaseRequest
    {
        $request = Mockery::mock(PurchaseRequest::class);
        $request->shouldReceive('user')->andReturn($this->buyer);
        $request->shouldReceive('integer')->with('quantity')->andReturn($quantity);
        $request->shouldReceive('validated')->with('amount_paid')->andReturn($amountPaid);

        return $request;
    }
}
