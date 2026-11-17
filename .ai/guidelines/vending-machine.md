# Vending Machine — Project Rules

Take-home assignment for a Senior Backend Developer role. Laravel 13 + Inertia 3 + React + TypeScript (React starter kit), MySQL 8.
Principle: real-world Laravel and well-known libraries first; implement literally only where it demonstrates understanding of the assignment (raw PDO repository for Task 2, attribute routes for Task 11).

## Architecture & layers

Request flow: `FormRequest` (validation) → `Controller` (HTTP only) → `Service` (only when business logic exists) → `Repository` (interface) → `Model`.

- **Controllers are thin**: read validated input, call a repository or service, return an Inertia response / redirect / API resource. No queries, no business rules, no money math in controllers.
- **Services only where there is business logic**: `PurchaseService` (stock, money, locking, transaction logging), `JwtService` (issue/verify tokens). Plain CRUD goes Controller → Repository directly. Do not create a service per model "just because".
- **Repositories** wrap all database access behind interfaces in `app/Repositories/Contracts`, so controllers and services can be unit-tested with mocks and no database.
    - `Eloquent/EloquentProductRepository` (Eloquent).
    - `Pdo/PdoTransactionRepository` (raw PDO prepared statements; the `\PDO` instance comes from Laravel's connection so it shares the same DB transaction).
    - Bind interface → implementation in `app/Providers/RepositoryServiceProvider.php`.
- **Dependency injection**: constructor injection with promoted `private readonly` properties; type-hint interfaces, never concrete repositories. No `new` for collaborators, no `app()` / `resolve()` inside classes, avoid facades inside services/repositories (inject `Illuminate\Database\ConnectionInterface` instead of `DB::`).
- Do **not** mark classes `final` if they are mocked in tests (Mockery cannot mock final classes).
- Web (Inertia) and API controllers share the same services/repositories; only the response format differs.

## Folder map

```
app/Http/Controllers/            ProductsController, TransactionsController
app/Http/Controllers/Api/V1/     AuthController, ProductController, TransactionController
app/Http/Requests/               StoreProductRequest, UpdateProductRequest, PurchaseRequest, ProductIndexRequest
app/Http/Resources/              ProductResource, TransactionResource
app/Models/                      User (HasRoles), Product, Transaction
app/Policies/                    ProductPolicy, TransactionPolicy
app/Repositories/{Contracts,Eloquent,Pdo}/
app/Services/                    PurchaseService, JwtService
app/Data/                        ProductListQuery (sort/direction/perPage/q + whitelist)
app/Exceptions/Domain/           InsufficientStockException, InsufficientFundsException, ProductUnavailableException
app/Support/                     Money (bcmath)
```

## Money

- Columns `DECIMAL(10,3)` (totals `DECIMAL(12,3)`), model casts `decimal:3`. Pepsi is **6.885** — never round to 2 decimals.
- All arithmetic through `App\Support\Money` (bcmath strings). **Never use floats** for money in PHP or in API responses (return strings like `"6.885"`).
- Frontend math is display-only (preview); the server is authoritative.

## Purchase rules

- Inside one DB transaction: `SELECT ... FOR UPDATE` the product, check availability, stock and funds, decrement stock, insert transaction with a **price snapshot** (`unit_price`, `total_price`, `amount_paid`, `change_given`).
- Failures are domain exceptions; web maps them to validation-style errors, API maps them to 409 (stock/unavailable) / 422 (funds).

## Auth & authorization

- Web auth: Laravel Fortify (session + bcrypt). Passkeys, 2FA, email verification and password reset are removed.
- RBAC: `spatie/laravel-permission`. Roles `admin`, `user`; permissions `products.manage`, `products.purchase`, `transactions.view-all`.
- Check **permissions**, not role names (role names only in seeders). Enforce server-side with `permission:` middleware and policies; hiding buttons in React is cosmetic only.
- Never store roles/permissions in the session or the JWT (stale privileges). Load from DB/cache.
- API: JWT HS256 via `firebase/php-jwt` + custom guard; claims `iss, sub, iat, exp, jti`; reject other algorithms.

## Routing

- Resource routes in `routes/web.php` and `routes/api.php` (`/api/v1`).
- Purchase actions use attribute routes (`spatie/laravel-route-attributes`) with slug URLs: `/products/{product:slug}/purchase`.
- Sort/filter inputs go through a whitelist (`ProductListQuery`); never pass user input to `orderBy` directly.

## Frontend

- Inertia + React + TypeScript; typed page props; reuse the kit's `resources/js/components/ui/*` (do not add new shadcn components).
- Use Wayfinder route helpers instead of hard-coded URLs.
- Client validation in `resources/js/lib/validation.ts` mirrors the FormRequest rules; show both client and server errors with the kit's `InputError` component.
- Never use `dangerouslySetInnerHTML`.

## Testing

- PHPUnit. Controller and service **unit tests use Mockery mocks and no database** (no `RefreshDatabase`).
- Feature tests (RefreshDatabase, MySQL) cover permissions, validation, purchase flow, API auth.
- Every bug fix or new behavior gets a test. Run `php artisan test`, `vendor/bin/pint`, `npm run types:check` before committing.

## Conventions

- Conventional commits (`feat(scope):`, `fix:`, `test:`, `docs:`, `chore:`), small and focused.
- Code, comments, commits and docs in English.
- Keep it simple: no features beyond the plan; explain trade-offs in `docs/DECISIONS.md`.
