# Layer templates

Copy the shape, not the exact names. Keep `declare(strict_types=1);` in every new PHP file.

## Contents

1. Repository interface
2. Eloquent repository
3. Provider binding
4. DTO (list query with whitelist)
5. FormRequest
6. Web controller (Inertia)
7. API controller
8. Service with injected connection
9. Domain exception
10. Money helper

---

## 1. Repository interface

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Data\ProductListQuery;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Product> */
    public function paginate(ProductListQuery $query): LengthAwarePaginator;

    public function findOrFail(int $id): Product;

    /** Must be called inside a DB transaction. Locks the row (SELECT ... FOR UPDATE). */
    public function findForUpdate(int $id): ?Product;

    /** @param array{name: string, price: string, quantity_available: int} $attributes */
    public function create(array $attributes): Product;

    /** @param array{name?: string, price?: string, quantity_available?: int} $attributes */
    public function update(Product $product, array $attributes): Product;

    public function delete(Product $product): void;

    public function decrementStock(Product $product, int $quantity): void;
}
```

## 2. Eloquent repository

```php
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
            ->orderBy($query->sort, $query->direction)   // already whitelisted by the DTO
            ->orderBy('id')                              // stable order across pages
            ->paginate($query->perPage)
            ->withQueryString();
    }

    public function findForUpdate(int $id): ?Product
    {
        return Product::query()->whereKey($id)->lockForUpdate()->first();
    }

    public function decrementStock(Product $product, int $quantity): void
    {
        $product->decrement('quantity_available', $quantity);
    }

    // create / update / delete / findOrFail: one Eloquent call each.
}
```

## 3. Provider binding

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Pdo\PdoTransactionRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProductRepositoryInterface::class => EloquentProductRepository::class,
        TransactionRepositoryInterface::class => PdoTransactionRepository::class,
    ];
}
```

Register it in `bootstrap/providers.php`.

## 4. DTO (list query with whitelist)

User input never reaches `orderBy` directly.

```php
<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ProductListQuery
{
    public const SORTABLE = ['name', 'price', 'quantity_available', 'created_at'];
    public const PER_PAGE = [5, 10, 25];

    public function __construct(
        public string $sort = 'name',
        public string $direction = 'asc',
        public int $perPage = 10,
        public ?string $search = null,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $sort = in_array($input['sort'] ?? null, self::SORTABLE, true) ? $input['sort'] : 'name';
        $direction = ($input['direction'] ?? null) === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) ($input['per_page'] ?? 0), self::PER_PAGE, true) ? (int) $input['per_page'] : 10;
        $search = trim((string) ($input['q'] ?? '')) ?: null;

        return new self($sort, $direction, $perPage, $search);
    }
}
```

DTOs are `final readonly` — they are values, never mocked.

## 5. FormRequest

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('products', 'name')->withoutTrashed()],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999.999', 'decimal:0,3'],
            'quantity_available' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

Update request: same rules, `->ignore($this->route('product'))` on unique. Mirror every rule in `resources/js/lib/validation.ts`.

## 6. Web controller (Inertia)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ProductListQuery;
use App\Exceptions\Domain\DomainException;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

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
            'filters' => ['sort' => $query->sort, 'direction' => $query->direction, 'per_page' => $query->perPage, 'q' => $query->search],
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->products->create($request->validated());

        return to_route('products.index')->with('success', 'Product created.');
    }

    // purchase(): see vending-assessment-requirements for the attribute route
    public function purchase(PurchaseRequest $request, Product $product): RedirectResponse
    {
        try {
            $receipt = $this->purchases->purchase(
                $request->user(), $product->id, $request->integer('quantity'), (string) $request->validated('amount_paid'),
            );
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$e->errorKey() => $e->getMessage()]);
        }

        return to_route('products.purchase', $product)->with('receipt', $receipt->toArray());
    }
}
```

## 7. API controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Data\ProductListQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->products->paginate(ProductListQuery::fromArray($request->query())));
    }
}
```

API controllers don't catch domain exceptions — their `render()` produces the JSON error.

## 8. Service with injected connection

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PurchaseReceipt;
use App\Exceptions\Domain\InsufficientFundsException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\ProductUnavailableException;
use App\Models\User;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Support\Money;
use Illuminate\Database\ConnectionInterface;

class PurchaseService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ProductRepositoryInterface $products,
        private readonly TransactionRepositoryInterface $transactions,
    ) {}

    public function purchase(User $user, int $productId, int $quantity, string $amountPaid): PurchaseReceipt
    {
        return $this->db->transaction(function () use ($user, $productId, $quantity, $amountPaid): PurchaseReceipt {
            $product = $this->products->findForUpdate($productId)
                ?? throw new ProductUnavailableException;

            if ($product->quantity_available < $quantity) {
                throw new InsufficientStockException($product->quantity_available);
            }

            $total = Money::mul($product->price, $quantity);

            if (Money::lt($amountPaid, $total)) {
                throw new InsufficientFundsException($total);
            }

            $this->products->decrementStock($product, $quantity);

            $transactionId = $this->transactions->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,          // snapshot
                'total_price' => $total,
                'amount_paid' => Money::normalize($amountPaid),
                'change_given' => Money::sub($amountPaid, $total),
            ]);

            return new PurchaseReceipt($transactionId, $product->name, $quantity, $product->price, $total, Money::normalize($amountPaid), Money::sub($amountPaid, $total));
        });
    }
}
```

`$this->db` is the default connection — bound automatically (`ConnectionInterface` resolves to `db.connection`). In unit tests, mock it with `->shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb())`.

## 9. Domain exception

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

abstract class DomainException extends RuntimeException
{
    abstract public function errorKey(): string;

    abstract public function status(): int;

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null; // let Laravel / the web controller handle it
        }

        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [$this->errorKey() => [$this->getMessage()]],
        ], $this->status());
    }
}

class InsufficientStockException extends DomainException
{
    public function __construct(public readonly int $available)
    {
        parent::__construct("Only {$available} left in stock.");
    }

    public function errorKey(): string { return 'quantity'; }

    public function status(): int { return 409; }
}
```

(One class per file in the real code.)

## 10. Money helper

```php
<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    private const SCALE = 3;

    public static function mul(string $amount, int $quantity): string
    {
        return bcmul($amount, (string) $quantity, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function lt(string $a, string $b): bool
    {
        return bccomp($a, $b, self::SCALE) < 0;
    }

    public static function normalize(string $amount): string
    {
        return bcadd($amount, '0', self::SCALE);   // "5" -> "5.000"
    }
}
```

`Money` is `final` and static on purpose: pure functions, tested directly, never mocked.
