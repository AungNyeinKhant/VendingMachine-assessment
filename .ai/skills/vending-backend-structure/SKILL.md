---
name: vending-backend-structure
description: "Project architecture for this vending machine app: how to add or change backend features using FormRequest → thin Controller → Service (only for business logic) → Repository interface → Model, with constructor dependency injection. Use this skill whenever you create or modify a controller, service, repository, model, migration, form request, DTO, domain exception, service provider binding, or money/price logic in this project — products, purchases, transactions, users, or the API. Also use it when deciding where a piece of logic belongs, or when the general laravel-best-practices advice (e.g. 'call Eloquent from the controller') conflicts with this project's layering; this skill wins for architecture."
---

# Vending machine — backend structure

This project is a take-home assessment for a senior backend role. Reviewers will read the code to judge architecture, so **consistency matters more than cleverness**: every feature should look like it was written by the same person following the same rules.

The assessment explicitly asks for dependency injection and for controller tests that run **without the database** (Task 15). That is the reason this project has a repository layer, which many Laravel apps skip. Keep that reason in mind: layers exist to make code testable and the purchase logic safe, not for ceremony.

## The layers and what each one owns

```
Request ─► FormRequest ─► Controller ─► Service ─► Repository (interface) ─► Model / PDO
           validation     HTTP only     business    database access
                                        rules
```

| Layer | Owns | Must not contain |
|---|---|---|
| `FormRequest` | Validation rules, authorization shortcut (`authorize()` may defer to policy) | Business logic |
| Controller | Reading validated input, calling one repository/service method, returning Inertia/redirect/API resource, mapping domain exceptions to HTTP | Queries, money math, stock checks, `DB::` calls |
| Service | Business rules spanning several steps or models (purchase, JWT) | HTTP concerns (requests, redirects, JSON) |
| Repository | All persistence; returns models/DTOs | Business rules, validation |
| Model | Relationships, casts, scopes, slug generation | Cross-model workflows |

### When do I need a service?

Ask: *is there a rule that could be wrong?* If the method is "validate, then save" there is no rule — the controller calls the repository directly. If there are checks, calculations, locking, or several writes that must succeed together, it belongs in a service.

- Product create/update/delete/list → Controller → `ProductRepositoryInterface` (no service)
- Purchase (lock row, check stock, check funds, compute change, decrement, log transaction) → `PurchaseService`
- Token issue/verify → `JwtService`

Don't create `ProductService` just to forward calls to the repository — reviewers read that as cargo-cult layering.

## Dependency injection rules

- Constructor injection with promoted `private readonly` properties.
- Type-hint **interfaces** for repositories (`ProductRepositoryInterface`), never the concrete class — that's what lets tests swap in mocks.
- No `new` for collaborators, no `app()`/`resolve()` inside classes, no facades inside services or repositories. Inject `Illuminate\Database\ConnectionInterface` instead of using `DB::transaction()` so the service is unit-testable.
- Bind interfaces in `app/Providers/RepositoryServiceProvider.php` (registered in `bootstrap/providers.php`).
- Don't mark a class `final` if tests mock it — Mockery cannot mock final classes. Services and repositories therefore stay non-final.
- Web (Inertia) and API controllers inject the **same** services/repositories. Only the response shape differs.

## Folder map

```
app/Http/Controllers/            ProductsController, TransactionsController
app/Http/Controllers/Api/V1/     AuthController, ProductController, TransactionController
app/Http/Requests/               StoreProductRequest, UpdateProductRequest, PurchaseRequest, ProductIndexRequest
app/Http/Resources/              ProductResource, TransactionResource
app/Models/                      User, Product, Transaction
app/Policies/                    ProductPolicy, TransactionPolicy
app/Repositories/Contracts/      ProductRepositoryInterface, TransactionRepositoryInterface
app/Repositories/Eloquent/       EloquentProductRepository
app/Repositories/Pdo/            PdoTransactionRepository          (literal PDO demo, see vending-assessment-requirements)
app/Database/                    PdoConnection                     (literal PDO demo)
app/Services/                    PurchaseService, JwtService
app/Data/                        ProductListQuery, PurchaseReceipt
app/Exceptions/Domain/           DomainException (base), InsufficientStockException, InsufficientFundsException, ProductUnavailableException
app/Support/                     Money
app/Providers/                   RepositoryServiceProvider
```

New classes go into the matching folder. If something doesn't fit, ask before inventing a new top-level folder.

## Money — the trap in this assignment

Pepsi costs **6.885 USD** (three decimals). Rounding it to 6.89 anywhere is a correctness bug a reviewer will look for.

- DB: `DECIMAL(10,3)` for prices, `DECIMAL(12,3)` for totals; model casts `decimal:3` (these come back as strings).
- PHP: all arithmetic through `App\Support\Money` (bcmath on numeric strings, scale 3). Never cast money to `float`, never use `round()`, `*`, `+`, `-` on prices.
- API: money serialized as strings (`"6.885"`).
- Frontend math is a preview only; the server result is authoritative.

## Domain exceptions

Business failures are typed exceptions extending `App\Exceptions\Domain\DomainException`, each with a user-facing message, an error key (`quantity`, `amount_paid`), and an HTTP status for the API (409 stock/unavailable, 422 funds).

- Web controllers catch them and rethrow `ValidationException::withMessages([$e->errorKey() => $e->getMessage()])` so the message lands in Inertia's `form.errors` next to the right field.
- API: the base exception's `render()` returns the standard JSON envelope `{ "message": ..., "errors": { key: [message] } }` with its status when `$request->expectsJson()`.

## Adding a feature — checklist

1. Migration + model (casts, relationships). Money columns as above.
2. Repository: add the method to the interface first, then implement it. Keep query details (sorting, locking, pagination) here.
3. Service only if the "is there a rule?" test says so.
4. FormRequest with rules; mirror them in `resources/js/lib/validation.ts` (client-side, Task 13).
5. Controller method: validated input → one call → response. Authorize with `permission:` middleware or a policy; check permissions, never role names.
6. Routes: resource routes in `routes/web.php` / `routes/api.php`; purchase actions use attribute routes (see vending-assessment-requirements).
7. Tests: controller unit test with mocks + a feature test (see vending-testing).
8. Run `php artisan test --compact`, `vendor/bin/pint --dirty`, `npm run types:check`.

Code templates for every layer (interface, Eloquent repository, provider binding, FormRequest, web/API controller, service with `ConnectionInterface`, DTO, domain exception, Money) are in [references/templates.md](references/templates.md). Read it before writing a new class of a kind you haven't written yet in this session, and copy its shape.
