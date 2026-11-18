# Decisions Log

## Requirement → Implementation Table

| Task                     | Approach | How                                                                                                                                                                                                                                                                                                                                                                          |
| ------------------------ | -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1 DB design              | ✍️       | Migrations + `docs/DATABASE.md` (ERD, field rationale)                                                                                                                                                                                                                                                                                                                       |
| 2 PDO + CRUD             | ✍️ + 📦  | `App\Database\PdoConnection` (thin wrapper over Laravel's PDO handle) + `PdoTransactionRepository` and `PdoUserRepository` (raw PDO prepared statements); products via Eloquent repo                                                                                                                                                                                         |
| 3 Sessions + hashing     | 📦       | Laravel Fortify — session guard + `Hash` (bcrypt). Login throttling built in                                                                                                                                                                                                                                                                                                 |
| 4 RBAC                   | 📦 + 💬  | `spatie/laravel-permission`. Roles `admin`, `user`; permissions `products.manage`, `products.purchase`, `transactions.view-all`. Code checks **permissions**, never role names (except seeding). **Role is NOT stored in session or JWT** — a session-cached role survives demotion until logout (stale privilege). Spatie reads from DB with cache it invalidates on change |
| 5–6 ProductsController   | ✍️       | Thin controller → `PurchaseService`                                                                                                                                                                                                                                                                                                                                          |
| 7–9 Views                | 📦 + 💬  | Inertia + React + TS pages rendered by controllers (`Inertia::render`) = server-routed "views"                                                                                                                                                                                                                                                                               |
| 10 Routing               | 📦       | `routes/web.php`, `routes/api.php`                                                                                                                                                                                                                                                                                                                                           |
| 11 Attribute routing     | ✍️ + 📦  | `#[Get]`/`#[Post]` on `ProductsController` purchase actions (`spatie/laravel-route-attributes`)                                                                                                                                                                                                                                                                              |
| 12–13 Validation         | 📦 + ✍️  | FormRequests (server) + `resources/js/lib/validation.ts` mirror (client)                                                                                                                                                                                                                                                                                                     |
| 14–15 Tests, DI, mocking | ✍️       | Constructor injection of repository interfaces / services; Mockery; controller unit tests without DB                                                                                                                                                                                                                                                                         |
| 16–17 REST API + JWT     | ✍️ + 📦  | `/api/v1`, custom JWT guard built on `firebase/php-jwt`; Scribe docs at `/docs`                                                                                                                                                                                                                                                                                              |

Legend: ✍️ implemented literally · 📦 fulfilled via framework/library · 💬 deliberately different, with reasoning

---

## Architecture Decision Records (ADRs)

### ADR-001: Laravel + Libraries vs Plain PHP

**Decision**: Use Laravel 13 with ecosystem libraries (Fortify, Spatie Permission, Scribe, Wayfinder).
**Reasoning**: Production-grade, battle-tested, reduces boilerplate, demonstrates senior judgement (knowing what not to build). The assignment asks for "a PHP application" — Laravel is a PHP application.

### ADR-002: Fortify vs Hand-written Auth

**Decision**: Use Laravel Fortify for session auth (login, register, password change, profile).
**Reasoning**: Fortify is maintained by Laravel core team, handles CSRF, throttling, password hashing, session regeneration. Hand-rolling auth is a security risk and time sink. Removed unused features (2FA, passkeys, email verification, password reset) via `php artisan install:features`.

### ADR-003: Spatie Permissions + Why Role NOT in Session/JWT

**Decision**: Use `spatie/laravel-permission` for RBAC. Check permissions (`can()`, `permission:` middleware, policies). Never store role in session or JWT.
**Reasoning**: A role cached in session survives an admin's demotion until logout — a stale-privilege bug. Spatie reads from DB with a cache it invalidates on permission change. Same for JWT: permissions loaded from DB on each request (or cached with short TTL). The `User` model sets `$guard_name = 'web'` so the same permissions work for both `web` and `api` (JWT) guards.

### ADR-004: Repository Layer (Why Here, When Not)

**Decision**: Repository interfaces (`ProductRepositoryInterface`, `TransactionRepositoryInterface`, `UserRepositoryInterface`) with Eloquent and PDO implementations, bound in `RepositoryServiceProvider`.
**Reasoning**: The assessment explicitly requires controller tests without database (Task 15). Eloquent static calls (`Product::create()`) can't be swapped for mocks; an injected interface can. In a typical Laravel app we'd test with feature tests against a test DB instead — but the requirement drives the architecture.

### ADR-005: PDO Repository (Task 2)

**Decision**: `App\Database\PdoConnection` (thin wrapper over Laravel's PDO handle) + `PdoTransactionRepository` and `PdoUserRepository` (raw PDO prepared statements). The PDO handle comes from Laravel's connection so it shares the same DB transaction.
**Reasoning**: Demonstrates literal understanding of Task 2 ("connection class", "CRUD using PDO") without duplicating connection config or breaking transaction atomicity. Products stay on Eloquent — the contrast (same interface, two implementations) is the demonstration.
**Users via PDO**: registration (`CreateNewUser`), API login (`AuthController`) and the JWT guard read and create users through `UserRepositoryInterface`. Profile/password updates stay on the starter kit's Eloquent code.
**No update/delete for transactions**: transactions are an immutable audit log (price snapshot), so the repository deliberately offers only create and read.

### ADR-006: Row Locking (Concurrency)

**Decision**: `SELECT ... FOR UPDATE` inside `PurchaseService::purchase()` via `ProductRepositoryInterface::findForUpdate()`.
**Reasoning**: Prevents overselling when two users buy the last item concurrently. The row lock is held for the duration of the DB transaction.

### ADR-007: bcmath for Money

**Decision**: All money arithmetic through `App\Support\Money` (bcmath on numeric strings, scale 3). Never floats.
**Reasoning**: Pepsi = 6.885 USD. Float math would introduce rounding errors (e.g., 0.1 + 0.2 ≠ 0.3). `DECIMAL(10,3)` in DB, strings in PHP/JSON.

### ADR-008: Custom JWT Guard

**Decision**: `firebase/php-jwt` (HS256 only) + `Auth::viaRequest('jwt', ...)` + custom `api` guard. Claims: `iss`, `sub`, `iat`, `exp` (60 min), `jti`. No role/permissions in token.
**Reasoning**: Task 17 asks for "token-based authentication (e.g., JWT)". `firebase/php-jwt` is small, audited, and understandable. HS256 with pinned algorithm (`new Key($secret, 'HS256')`) rejects `alg: none`/RS swaps. No role in token — same stale-privilege reasoning as ADR-003.

### ADR-009: Inertia + Separate REST API

**Decision**: Web app = Inertia (session auth, server-routed SPA). API = `/api/v1` (JWT auth, JSON only, for mobile/machine clients).
**Reasoning**: The assessment asks for "views" (Tasks 7–9) AND "RESTful API" (Task 16). They serve different clients. Inertia gives SPA UX with server-side routing; API gives programmatic access. Scribe docs at `/docs` let reviewers test the API live.

### ADR-010: Attribute Routing (Task 11)

**Decision**: `spatie/laravel-route-attributes` on purchase actions only: `#[Get('/products/{product:slug}/purchase')]`, `#[Post(...)]`. All other routes in `routes/web.php`.
**Reasoning**: Literal implementation of "attribute routing" (like .NET `[HttpGet]`) where it demonstrates understanding (SEO-friendly slug URLs). Rest of routes stay in route files — idiomatic Laravel.

---

## Out of Scope (With More Time)

- **Cart/Checkout**: Multi-item checkout would accept only `[{product_id, quantity}]`, lock rows in id order (deadlock-safe), price everything server-side, all-or-nothing transaction.
- **Payment Gateway**: Stripe/coin integration.
- **Coin/Denomination Inventory**: Physical machine coin tracking.
- **Refresh Tokens & Token Revocation**: JWT blacklist/rotation.
- **Email Features**: Verification, password reset, notifications.
- **Admin User Management UI**: CRUD for users/roles.
- **i18n**: Multi-language support.
