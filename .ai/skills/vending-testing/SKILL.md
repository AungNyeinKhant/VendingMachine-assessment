---
name: vending-testing
description: "How to write PHPUnit tests in this vending machine project: controller and service unit tests that use dependency injection and Mockery mocks with NO database (assessment Tasks 14-15), plus feature tests for permissions, validation, purchase flow, Inertia pages and the JWT API. Use this skill whenever you add or change a test, add a feature that needs tests, test ProductsController, PurchaseService, Money, repositories, policies or API endpoints, or when a test touches mocks, Inertia assertions, spatie permissions, or JWT tokens in this project. Complements the generic testing-best-practices skill; for this project's structure this skill wins."
---

# Vending machine — testing

The assessment grades tests explicitly:
- **Task 14:** unit tests for `ProductsController` covering scenarios and edge cases.
- **Task 15:** use dependency injection and mocking to test controller actions **independently of the database and other services**.

So this project has two kinds of tests, and the difference is the point:

| Kind | Folder | Database | What it proves |
|---|---|---|---|
| Unit | `tests/Unit/...` | **None.** No `RefreshDatabase`, no factories that persist, no `DB::` | One class's behavior in isolation, collaborators mocked |
| Feature | `tests/Feature/...` | Yes (`RefreshDatabase`) | The wired-up system: routes, middleware, permissions, SQL, PDO repo |

A unit test that quietly hits the database defeats Task 15 — if you find yourself creating a model with `->create()` in `tests/Unit`, use `->make()` / `new Product([...])` instead, or move the test to Feature.

## Conventions (match the starter kit)

- PHPUnit classes extending `Tests\TestCase`, methods named `test_snake_case_description`. Not Pest.
- One behavior per test; name says the scenario and the outcome: `test_purchase_with_insufficient_funds_returns_error_on_amount_paid`.
- Arrange / act / assert separated by blank lines.
- Run the narrowest set first: `php artisan test --compact --filter=ProductsControllerTest`, then the whole suite before committing.

## Unit test: controller with mocks

Pattern: mock the interfaces the controller's constructor asks for, construct the controller with `new`, call the action, assert on the response and on the mock expectations.

- Mock **interfaces** (`ProductRepositoryInterface`) and non-final services (`PurchaseService`).
- FormRequests: mock them too — `Mockery::mock(StoreProductRequest::class)` with `validated()` (and `user()`, `integer()` as needed) stubbed. That keeps validation out of the controller unit test; validation is covered by feature tests.
- Inertia responses: convert to an HTTP response with an `X-Inertia` request, wrap it in `TestResponse`, then use `assertInertia`.
- Domain failures: make the service mock throw (`->andThrow(new InsufficientStockException(2))`) and assert the controller turns it into a `ValidationException` with the right error key.

Full examples: [references/unit-tests.md](references/unit-tests.md).

### Edge cases worth a test (Task 14 asks for them)
- index: invalid `sort` (`password`, `id; drop`) falls back to `name`; invalid `per_page` falls back to 10; `direction` other than `desc` → `asc`.
- store/update/destroy: repository called once with validated data; redirect + flash.
- purchase: success → redirect with receipt; insufficient stock → error on `quantity`; insufficient funds → error on `amount_paid`; product unavailable → error. Don't re-test validation rules here — that's the FormRequest's job and belongs in feature tests.
- `PurchaseService`: Pepsi 6.885 × 3 = `"20.655"`; exact payment → change `"0.000"`; one cent short → `InsufficientFundsException`; quantity > stock → `InsufficientStockException` and **no** decrement / transaction insert (`shouldNotReceive`); locked product missing → `ProductUnavailableException`.
- `Money`: no float drift (`0.1 + 0.2` style cases), normalization `"5"` → `"5.000"`.

## Feature tests

- `use RefreshDatabase;` and seed permissions in `setUp()`: `$this->seed(RolesAndPermissionsSeeder::class);` — spatie caches permissions, so also call `app(PermissionRegistrar::class)->forgetCachedPermissions();`.
- Create users with roles: `User::factory()->create()->assignRole('admin')`.
- Inertia pages: `->assertInertia(fn (Assert $page) => $page->component('products/index')->has('products.data', 3))`.
- Authorization: assert `403` for a `user` on every manage route and `302 → login` for guests. Test the server, not hidden buttons.
- Purchase: assert stock decremented, one `transactions` row with `unit_price` snapshot and correct `change_given` — this also exercises the raw-PDO repository end to end.
- API: get a token from `app(JwtService::class)->issue($user)['access_token']` and call with `->withToken($token)->postJson(...)`. Cover missing token (401), expired token (encode one with past `exp`), tampered signature (change the last character), wrong algorithm, and `user` on admin endpoints (403). Assert JSON envelope shape (`message`, `errors`).
- Money in JSON is a string: `->assertJsonPath('data.price', '6.885')`.

Examples: [references/feature-tests.md](references/feature-tests.md).

## Database for tests

`phpunit.xml` uses SQLite in-memory by default (fast). SQLite ignores `lockForUpdate` and is loose with DECIMAL, so CI runs the suite against MySQL 8 (`DB_CONNECTION=mysql` in the workflow). If a test depends on MySQL behavior, say so in its name or a comment rather than letting it pass by accident on SQLite.

## Before committing

`php artisan test --compact` → all green, `vendor/bin/pint --dirty`, `npm run types:check`. A new feature without a unit test **and** a feature test is not done.
