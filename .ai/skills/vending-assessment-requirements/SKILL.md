---
name: vending-assessment-requirements
description: "Maps the 17 tasks of the Visible One vending machine PHP assessment to this Laravel codebase and holds the deliberate 'literal' implementations that prove the assessment was understood: the PDO connection class and raw-PDO transaction repository (Task 2), attribute routing for the purchase action (Task 11), session/role decisions (Tasks 3-4), and the JWT API guard (Task 17). Use this skill whenever you touch PDO, PdoConnection, PdoTransactionRepository, the transactions table, purchase routes, #[Get]/#[Post] route attributes, spatie route attributes or permissions, JWT, the api guard, roles, or docs/DECISIONS.md — and whenever you wonder whether to implement an assessment task literally or the idiomatic Laravel way."
---

# Vending machine — assessment requirements

The assessment PDF was written for "a PHP application" (it reads like a translated ASP.NET MVC task: "ProductsController", "attribute routing", "views"). We build it with Laravel and well-known libraries because that is what a real team would ship — but a reviewer will check each numbered task. So we follow one principle:

> **Real-world Laravel first. Implement literally where it is cheap and demonstrates understanding. Where the literal reading would weaken the design, don't — and explain why in `docs/DECISIONS.md`.**

Never quietly drop a task. Every task is either implemented literally (✍️), fulfilled through the framework/library (📦), or deliberately done differently with a reason (💬).

## Requirement map

| # | Task (short) | How | Where |
|---|---|---|---|
| 1 | Describe DB setup | ✍️ | migrations, `docs/DATABASE.md` |
| 2 | PDO connection class + CRUD | ✍️ + 📦 | `App\Database\PdoConnection`, `PdoTransactionRepository` (raw PDO); products via Eloquent repo; users via Fortify |
| 3 | Sessions + password hashing | 📦 | Fortify (session guard, bcrypt) |
| 4 | Role checks "stored in the session" | 📦 + 💬 | spatie permissions read from DB/cache, not session |
| 5–6 | ProductsController CRUD + purchase | ✍️ | `ProductsController`, `PurchaseService` |
| 7–9 | Views (CRUD, list, purchase) | 📦 + 💬 | Inertia React pages rendered by controllers |
| 10 | Routing | 📦 | `routes/web.php`, `routes/api.php` |
| 11 | Attribute routing for purchase | ✍️ + 📦 | `#[Get]`/`#[Post]` on `ProductsController` |
| 12–13 | Validation server + client | 📦 + ✍️ | FormRequests + `resources/js/lib/validation.ts` |
| 14–15 | PHPUnit, DI, mocking | ✍️ | see vending-testing |
| 16–17 | REST API + JWT | ✍️ + 📦 | `/api/v1`, `JwtService` + custom guard |

When you implement or change any of these, update the matching row in `docs/DECISIONS.md` so the documentation never drifts from the code.

## Task 2 — PDO, literally

Two small classes, both visible to a reviewer searching for "PDO":

- `App\Database\PdoConnection` — the "connection class". It does **not** open a second connection with duplicated credentials; it borrows the PDO handle from Laravel's connection (single config, and raw PDO statements join the same DB transaction as Eloquent). It exposes `select()`, `selectOne()`, `insert()`, `execute()` with prepared statements only.
- `App\Repositories\Pdo\PdoTransactionRepository implements TransactionRepositoryInterface` — CRUD for `transactions` written in SQL with named placeholders.

Rules that make it look senior rather than naive:
- Prepared statements with named placeholders for every value. Never interpolate input into SQL. Sort columns, if ever needed, come from a whitelist.
- Keep DECIMAL values as strings (PDO MySQL returns them as strings; don't cast to float).
- Because the PDO handle is shared, `PurchaseService`'s `$db->transaction()` covers the PDO insert too — say so in a comment, it's the point of the design.
- The rest of the app still uses Eloquent. Don't convert other repositories to PDO "for consistency" — the contrast (same interface, two implementations) is the demonstration.

Templates: [references/pdo.md](references/pdo.md).

## Task 11 — attribute routing, literally

Package: `spatie/laravel-route-attributes`. Only the purchase actions use attributes; all other routes stay in route files. Attribute routes give the SEO-friendly slug URL `/products/{product:slug}/purchase`.

```php
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;

#[Get('/products/{product:slug}/purchase', name: 'products.purchase', middleware: ['web', 'auth', 'permission:products.purchase'])]
public function purchaseForm(Product $product): Response

#[Post('/products/{product:slug}/purchase', name: 'products.purchase.store', middleware: ['web', 'auth', 'permission:products.purchase', 'throttle:30,1'])]
public function purchase(PurchaseRequest $request, Product $product): RedirectResponse
```

Config (`config/route-attributes.php`): scan only `app_path('Http/Controllers')` **without** directory-level middleware — middleware lives on each attribute, so API controllers in subfolders are never accidentally given `web`. After changes run `php artisan route:list --path=products` and check there are no duplicates and the slug route resolves.

## Tasks 3–4 — sessions and roles

- Authentication: Fortify's session guard and `Hash` (bcrypt). Don't hand-write login — it's the library's job and reviewers in real teams expect it. The session identifies the user; Fortify regenerates the session ID on login.
- Authorization: `spatie/laravel-permission`. Roles `admin`, `user`; permissions `products.manage`, `products.purchase`, `transactions.view-all`. Code checks **permissions** (`can('products.manage')`, `permission:` middleware, policies), never `hasRole('admin')` outside seeders.
- **Why roles are not copied into the session or JWT (the 💬 for Task 4):** a role cached in the session survives a demotion until logout — a stale-privilege bug. Spatie reads from DB with a cache it invalidates on change. Keep this explanation in DECISIONS.md; don't "fix" it by writing `session(['role' => ...])`.
- Register spatie's middleware aliases in `bootstrap/app.php` (`->withMiddleware(fn ($m) => $m->alias(['permission' => PermissionMiddleware::class, 'role' => RoleMiddleware::class]))`) — Laravel 13 has no HTTP Kernel.
- `User` model sets `protected string $guard_name = 'web';` so the same permissions work for requests authenticated by the `api` (JWT) guard. Without it, spatie looks permissions up under guard `api` and every API permission check fails.

## Tasks 16–17 — REST API with JWT

- Routes under `/api/v1` in `routes/api.php`; JSON only; API Resources; money as strings.
- `firebase/php-jwt`, HS256 only. `JwtService::issue(User)` returns `{access_token, token_type: "Bearer", expires_in}`; claims `iss`, `sub`, `iat`, `exp` (60 min), `jti`. No role/permissions in the token (same reason as Task 4).
- Guard: `Auth::viaRequest('jwt', ...)` in a service provider + `'api' => ['driver' => 'jwt', 'provider' => 'users']` in `config/auth.php`. Protected routes use `auth:api`.
- Decoding with `new Key($secret, 'HS256')` pins the algorithm (rejects `alg: none`/RS swaps). Expired, tampered, or malformed tokens → no user → 401 JSON.
- Login is throttled (`throttle:5,1`).

Templates: [references/jwt.md](references/jwt.md).

## What not to do

- Don't hand-roll things a library does well just to look literal (auth, password hashing, JWT signing, routing engine).
- Don't add literal implementations beyond the two above without asking — each one must earn its place.
- Don't store roles in session/JWT, and don't remove the DECISIONS.md explanation.
