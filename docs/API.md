# API Reference

Interactive documentation with "Try it out": **`/docs`** (Scribe)

## Base URL

```
https://<your-app>.onrender.com/api/v1
```

## Authentication

- **Login**: `POST /auth/login` → returns `{ access_token, token_type: "Bearer", expires_in }`
- **Header**: `Authorization: Bearer <access_token>`
- **Token**: JWT HS256, 60 min expiry, claims `iss`, `sub`, `iat`, `exp`, `jti`

## Endpoints

### Auth

| Method | Path          | Auth | Description                |
| ------ | ------------- | ---- | -------------------------- |
| POST   | `/auth/login` | ❌   | Get JWT token              |
| GET    | `/auth/me`    | ✅   | Current user + permissions |

### Products (Public Read)

| Method | Path             | Auth | Description                     |
| ------ | ---------------- | ---- | ------------------------------- |
| GET    | `/products`      | ❌   | List (pagination, sort, search) |
| GET    | `/products/{id}` | ❌   | Single product                  |

### Products (Write)

| Method | Path             | Auth | Permissions       | Description |
| ------ | ---------------- | ---- | ----------------- | ----------- |
| POST   | `/products`      | ✅   | `products.manage` | Create      |
| PUT    | `/products/{id}` | ✅   | `products.manage` | Update      |
| DELETE | `/products/{id}` | ✅   | `products.manage` | Soft delete |

### Purchase

| Method | Path                      | Auth | Permissions         | Description |
| ------ | ------------------------- | ---- | ------------------- | ----------- |
| POST   | `/products/{id}/purchase` | ✅   | `products.purchase` | Buy product |

Body:

```json
{
    "quantity": 1,
    "amount_paid": "10.000"
}
```

Response (201):

```json
{
    "transaction_id": 1,
    "product_name": "Pepsi",
    "quantity": 3,
    "unit_price": "6.885",
    "total_price": "20.655",
    "amount_paid": "25.000",
    "change_given": "4.345"
}
```

### Transactions

| Method | Path            | Auth | Permissions                              | Description    |
| ------ | --------------- | ---- | ---------------------------------------- | -------------- |
| GET    | `/transactions` | ✅   | `transactions.view-all` (see all) or own | List purchases |

## Query Parameters (Products List)

| Param       | Values                                              | Default |
| ----------- | --------------------------------------------------- | ------- |
| `sort`      | `name`, `price`, `quantity_available`, `created_at` | `name`  |
| `direction` | `asc`, `desc`                                       | `asc`   |
| `per_page`  | `5`, `10`, `25`                                     | `10`    |
| `q`         | search string                                       | —       |

## Error Envelope

All errors return JSON:

```json
{
    "message": "Validation failed",
    "errors": {
        "field": ["Error message"]
    }
}
```

| Code | Scenario                                 |
| ---- | ---------------------------------------- |
| 401  | Missing/invalid/expired/tampered token   |
| 403  | Insufficient permissions                 |
| 404  | Not found                                |
| 409  | Insufficient stock / product unavailable |
| 422  | Validation error / insufficient funds    |

## Money Format

All monetary values are **strings** with 3 decimals:

```json
{ "price": "6.885", "total_price": "20.655", "change_given": "4.345" }
```

## Postman Collection

Download: [`docs/postman_collection.json`](postman_collection.json)

Import into Postman → set `base_url` variable → test all endpoints.
