# Unit test examples (no database)

## ProductsController

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Data\ProductListQuery;
use App\Data\PurchaseReceipt;
use App\Exceptions\Domain\InsufficientFundsException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Http\Controllers\ProductsController;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response as InertiaResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductsControllerTest extends TestCase
{
    private ProductRepositoryInterface&MockInterface $products;

    private PurchaseService&MockInterface $purchases;

    private ProductsController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->products = Mockery::mock(ProductRepositoryInterface::class);
        $this->purchases = Mockery::mock(PurchaseService::class);
        $this->controller = new ProductsController($this->products, $this->purchases);
    }

    public function test_index_falls_back_to_name_when_sort_column_is_not_whitelisted(): void
    {
        $this->products->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(fn (ProductListQuery $q) => $q->sort === 'name' && $q->direction === 'asc'))
            ->andReturn(new LengthAwarePaginator([], 0, 10));

        $response = $this->controller->index(Request::create('/products', 'GET', ['sort' => 'password']));

        $this->inertia($response)->assertInertia(fn (Assert $page) => $page
            ->component('products/index', shouldExist: false)
            ->where('filters.sort', 'name'));
    }

    public function test_store_persists_validated_data_and_redirects(): void
    {
        $data = ['name' => 'Coke', 'price' => '3.990', 'quantity_available' => 10];
        $request = Mockery::mock(StoreProductRequest::class);
        $request->shouldReceive('validated')->andReturn($data);

        $this->products->shouldReceive('create')->once()->with($data)->andReturn(new Product($data));

        $response = $this->controller->store($request);

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

    // --- helpers -------------------------------------------------------

    private function product(): Product
    {
        $product = new Product(['name' => 'Pepsi', 'slug' => 'pepsi', 'price' => '6.885', 'quantity_available' => 2]);
        $product->id = 2;   // set, never saved

        return $product;
    }

    private function purchaseRequest(int $quantity, string $amountPaid): PurchaseRequest
    {
        $request = Mockery::mock(PurchaseRequest::class);
        $request->shouldReceive('user')->andReturn(new User(['name' => 'Test']));
        $request->shouldReceive('integer')->with('quantity')->andReturn($quantity);
        $request->shouldReceive('validated')->with('amount_paid')->andReturn($amountPaid);

        return $request;
    }

    /** Turn an Inertia\Response into a TestResponse so assertInertia() works. */
    private function inertia(InertiaResponse $response): TestResponse
    {
        $request = Request::create('/', 'GET', server: ['HTTP_X_INERTIA' => 'true']);

        return TestResponse::fromBaseResponse($response->toResponse($request));
    }
}
```

`shouldExist: false` skips the check that the `.tsx` page file exists — remove it once the page is built. If `assertInertia` complains about the request, build the response with `$this->app->instance('request', $request)` first.

## PurchaseService

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\Domain\InsufficientFundsException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\PurchaseService;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use Tests\TestCase;

class PurchaseServiceTest extends TestCase
{
    public function test_buying_three_pepsi_keeps_three_decimals(): void
    {
        [$service, $products, $transactions] = $this->service($this->pepsi(stock: 10));

        $products->shouldReceive('decrementStock')->once();
        $transactions->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $row) => $row['total_price'] === '20.655' && $row['change_given'] === '0.000'))
            ->andReturn(1);

        $receipt = $service->purchase(new User, 2, 3, '20.655');

        $this->assertSame('20.655', $receipt->total);
    }

    public function test_insufficient_stock_writes_nothing(): void
    {
        [$service, $products, $transactions] = $this->service($this->pepsi(stock: 1));

        $products->shouldNotReceive('decrementStock');
        $transactions->shouldNotReceive('create');

        $this->expectException(InsufficientStockException::class);

        $service->purchase(new User, 2, 2, '100');
    }

    public function test_one_tenth_of_a_cent_short_is_rejected(): void
    {
        [$service] = $this->service($this->pepsi(stock: 10));

        $this->expectException(InsufficientFundsException::class);

        $service->purchase(new User, 2, 1, '6.884');
    }

    /** @return array{PurchaseService, \Mockery\MockInterface, \Mockery\MockInterface} */
    private function service(?Product $locked): array
    {
        $db = Mockery::mock(ConnectionInterface::class);
        $db->shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $products = Mockery::mock(ProductRepositoryInterface::class);
        $products->shouldReceive('findForUpdate')->andReturn($locked);

        $transactions = Mockery::mock(TransactionRepositoryInterface::class);

        return [new PurchaseService($db, $products, $transactions), $products, $transactions];
    }

    private function pepsi(int $stock): Product
    {
        $product = new Product(['name' => 'Pepsi', 'price' => '6.885', 'quantity_available' => $stock]);
        $product->id = 2;

        return $product;
    }
}
```

Note `new User` / `new Product([...])` — in-memory models, never saved. That's what keeps these tests DB-free.
