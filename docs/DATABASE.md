# Database Design (Task 1)

## Entity Relationship Diagram

```mermaid
erDiagram
    USERS ||--o{ TRANSACTIONS : "has many"
    PRODUCTS ||--o{ TRANSACTIONS : "has many"

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        timestamp remember_token
        timestamps created_at, updated_at
    }

    PRODUCTS {
        bigint id PK
        string name UK
        string slug UK
        decimal(10,3) price
        int quantity_available
        timestamps created_at, updated_at
        timestamp deleted_at
    }

    TRANSACTIONS {
        bigint id PK
        bigint user_id FK
        bigint product_id FK
        int quantity
        decimal(10,3) unit_price
        decimal(12,3) total_price
        decimal(12,3) amount_paid
        decimal(12,3) change_given
        timestamp created_at
    }
```

## Tables

### `users`

Standard Laravel users table (from starter kit) plus spatie permissions tables:

- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`

### `products`

| Column                     | Type          | Constraints                   | Rationale                                 |
| -------------------------- | ------------- | ----------------------------- | ----------------------------------------- |
| `id`                       | BIGINT        | PK, Auto Increment            | Large enough for scale                    |
| `name`                     | VARCHAR(100)  | UNIQUE, NOT NULL              | Business key, SEO                         |
| `slug`                     | VARCHAR(120)  | UNIQUE, NOT NULL              | SEO-friendly URLs (Task 11)               |
| `price`                    | DECIMAL(10,3) | UNSIGNED, NOT NULL            | **3 decimals for Pepsi 6.885**            |
| `quantity_available`       | INT           | UNSIGNED, NOT NULL, DEFAULT 0 | Stock cannot be negative                  |
| `created_at`, `updated_at` | TIMESTAMP     |                               | Audit trail                               |
| `deleted_at`               | TIMESTAMP     | NULLABLE                      | Soft delete preserves transaction history |

### `transactions`

| Column         | Type          | Constraints                          | Rationale                           |
| -------------- | ------------- | ------------------------------------ | ----------------------------------- |
| `id`           | BIGINT        | PK, Auto Increment                   |                                     |
| `user_id`      | BIGINT        | FK → users.id, ON DELETE RESTRICT    | Prevent orphan transactions         |
| `product_id`   | BIGINT        | FK → products.id, ON DELETE RESTRICT | Preserve price snapshot integrity   |
| `quantity`     | INT           | UNSIGNED, NOT NULL                   | >= 1                                |
| `unit_price`   | DECIMAL(10,3) | NOT NULL                             | **Price snapshot** at purchase time |
| `total_price`  | DECIMAL(12,3) | NOT NULL                             | unit_price × quantity               |
| `amount_paid`  | DECIMAL(12,3) | NOT NULL                             | Money inserted by customer          |
| `change_given` | DECIMAL(12,3) | NOT NULL                             | amount_paid - total_price           |
| `created_at`   | TIMESTAMP     | NOT NULL                             | Immutable audit timestamp           |

**Indexes**:

- `(user_id, created_at)` — user purchase history
- `(product_id, created_at)` — product sales analytics

## Key Design Decisions

### DECIMAL(10,3) for Prices

Pepsi costs **6.885 USD** (3 decimal places). `DECIMAL(10,2)` would round to 6.89 — a correctness bug.

- Prices: `DECIMAL(10,3)` (max 99999.999)
- Totals: `DECIMAL(12,3)` (max 99999999.999)
- Model casts: `decimal:3` (returned as strings)

### Price Snapshot

`unit_price`, `total_price`, `amount_paid`, `change_given` are copied at purchase time.
Later price changes **never** rewrite history.

### Soft Delete on Products

`deleted_at` keeps product rows for valid FK references in transactions.
`ON DELETE RESTRICT` on FKs prevents accidental data loss.

### Row Locking (Concurrency)

`SELECT ... FOR UPDATE` inside DB transaction prevents overselling:

```php
$product = Product::query()->whereKey($id)->lockForUpdate()->first();
```

## Seed Data

| Product | Price | Qty |
| ------- | ----- | --- |
| Coke    | 3.990 | 10  |
| Pepsi   | 6.885 | 10  |
| Water   | 0.500 | 20  |

Plus ~10 demo snacks for pagination visibility.

## Users

| Email                | Password   | Role                                                                |
| -------------------- | ---------- | ------------------------------------------------------------------- |
| `admin@vending.test` | `password` | `admin` (products.manage, products.purchase, transactions.view-all) |
| `user@vending.test`  | `password` | `user` (products.purchase)                                          |

Registration assigns `user` role automatically via Fortify's `CreateNewUser` action.
