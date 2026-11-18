# Vending Machine

Laravel 13 + Inertia 3 + React + TypeScript vending machine system for a Senior Backend Developer take-home assignment.

## Live Demo

- **URL**: https://vendingmachine-assessment.onrender.com (free tier: the first request after idle can take ~50 s)
- **Admin**: `admin@vending.test` / `password`
- **User**: `user@vending.test` / `password`
- **API Docs**: `/docs` (Scribe with "Try it out")

## Architecture

```
Request → FormRequest → Controller → Service → Repository → Model/PDO
```

- **Controllers**: Thin, HTTP only
- **Services**: Business logic (PurchaseService, JwtService)
- **Repositories**: Eloquent (products) + Raw PDO (transactions) behind interfaces
- **Money**: `DECIMAL(10,3)` + bcmath via `App\Support\Money` — never floats
- **Auth**: Fortify (session) + spatie/laravel-permission (RBAC)
- **API**: JWT HS256 via firebase/php-jwt + custom guard

## Quick Start (Local)

```bash
# 1. Start MySQL
docker compose up -d

# 2. Install dependencies
composer install
npm install

# 3. Configure .env (copy .env.example → .env, set DB credentials)
cp .env.example .env

# 4. Generate key & migrate
php artisan key:generate
php artisan migrate --seed

# 5. Build frontend
npm run build

# 6. Start dev server (Laravel Herd or)
php artisan serve
```

## Task Coverage (17 Tasks)

| #     | Task               | Implementation                                                                         |
| ----- | ------------------ | -------------------------------------------------------------------------------------- |
| 1     | DB Design          | Migrations + `docs/DATABASE.md` (ERD, rationale)                                       |
| 2     | PDO CRUD           | `App\Database\PdoConnection` + `PdoTransactionRepository` (raw PDO, shared connection) |
| 3     | Sessions + Hashing | Laravel Fortify (session guard, bcrypt)                                                |
| 4     | RBAC               | spatie/laravel-permission (permissions checked, not roles in session/JWT)              |
| 5–6   | ProductsController | CRUD + purchase via `PurchaseService`                                                  |
| 7–9   | Views              | Inertia React pages (products CRUD, list with pagination/sort, purchase)               |
| 10    | Routing            | `routes/web.php`, `routes/api.php`                                                     |
| 11    | Attribute Routing  | `#[Get]`/`#[Post]` on purchase actions (`spatie/laravel-route-attributes`)             |
| 12–13 | Validation         | FormRequests + `resources/js/lib/validation.ts` mirror                                 |
| 14–15 | Tests, DI, Mocking | Unit tests (mocked, no DB) + Feature tests (MySQL)                                     |
| 16–17 | REST API + JWT     | `/api/v1`, JWT HS256, Scribe docs at `/docs`                                           |

## Testing

```bash
# Unit tests (no DB, mocked)
php artisan test --testsuite=Unit

# Feature tests (MySQL via docker compose)
docker compose up -d
php artisan test --testsuite=Feature

# All tests + lint + types
composer ci:check
```

## Deployment

See [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) for Render + Aiven + UptimeRobot steps.

## CI/CD

GitHub Actions (`.github/workflows/tests.yml`): PHP 8.4, MySQL 8, Pint, PHPStan, PHPUnit.

## AI Assistance

This project was built with AI coding assistants (Claude Code for planning and code review, OpenCode for implementation). The architecture, the trade-offs in [docs/DECISIONS.md](docs/DECISIONS.md) and the final review are mine; every change was checked against the test suite and reviewed before merging.

## License

MIT
