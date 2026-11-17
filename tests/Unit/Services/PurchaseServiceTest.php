<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\Domain\InsufficientFundsException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\PurchaseService;
use Illuminate\Database\ConnectionInterface;
use Mockery;
use Mockery\MockInterface;
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

        $this->assertSame('20.655', $receipt->totalPrice);
    }

    public function test_exact_payment_results_in_zero_change(): void
    {
        [$service, $products, $transactions] = $this->service($this->pepsi(stock: 5));

        $products->shouldReceive('decrementStock')->once();
        $transactions->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $row) => $row['change_given'] === '0.000'))
            ->andReturn(1);

        $receipt = $service->purchase(new User, 2, 1, '6.885');

        $this->assertSame('0.000', $receipt->changeGiven);
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

    public function test_product_unavailable_throws_exception(): void
    {
        [$service, $products] = $this->service(null);

        $this->expectException(ProductUnavailableException::class);

        $service->purchase(new User, 999, 1, '10');
    }

    /** @return array{PurchaseService, MockInterface, MockInterface} */
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
