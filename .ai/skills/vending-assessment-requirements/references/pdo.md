# PDO templates (Task 2)

## PdoConnection — the connection class

```php
<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\ConnectionInterface;
use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper (assessment Task 2).
 *
 * Borrows the PDO handle from Laravel's connection instead of opening a second one:
 * credentials live in one place (config/database.php) and raw statements run inside
 * the same DB transaction as Eloquent queries.
 */
class PdoConnection
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function pdo(): PDO
    {
        $pdo = $this->connection->getPdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /** @param array<string, scalar|null> $params @return list<array<string, mixed>> */
    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, scalar|null> $params @return array<string, mixed>|null */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @param array<string, scalar|null> $params */
    public function insert(string $sql, array $params): int
    {
        $this->run($sql, $params);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, scalar|null> $params Returns affected rows. */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** @param array<string, scalar|null> $params */
    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}
```

`getPdo()` exists on `Illuminate\Database\Connection`; type-hint `ConnectionInterface` and rely on the default MySQL connection. Laravel resolves `ConnectionInterface` automatically, so no binding is needed.

## PdoTransactionRepository

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Database\PdoConnection;
use App\Repositories\Contracts\TransactionRepositoryInterface;

/**
 * Raw-PDO implementation of the transaction log (assessment Task 2).
 * Same interface as an Eloquent implementation would have — swap it in RepositoryServiceProvider.
 */
class PdoTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private readonly PdoConnection $db) {}

    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO transactions
                (user_id, product_id, quantity, unit_price, total_price, amount_paid, change_given, created_at)
             VALUES
                (:user_id, :product_id, :quantity, :unit_price, :total_price, :amount_paid, :change_given, :created_at)',
            [
                'user_id' => $data['user_id'],
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
                'unit_price' => $data['unit_price'],     // strings — DECIMAL precision preserved
                'total_price' => $data['total_price'],
                'amount_paid' => $data['amount_paid'],
                'change_given' => $data['change_given'],
                'created_at' => now()->toDateTimeString(),
            ],
        );
    }

    public function paginateForUser(?int $userId, int $perPage, int $page): array
    {
        $where = $userId === null ? '' : 'WHERE t.user_id = :user_id';
        $params = $userId === null ? [] : ['user_id' => $userId];

        $total = (int) $this->db->selectOne("SELECT COUNT(*) AS c FROM transactions t {$where}", $params)['c'];

        $rows = $this->db->select(
            "SELECT t.*, p.name AS product_name, u.name AS user_name
               FROM transactions t
               JOIN products p ON p.id = t.product_id
               JOIN users u ON u.id = t.user_id
             {$where}
              ORDER BY t.created_at DESC, t.id DESC
              LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return ['data' => $rows, 'total' => $total];
    }
}
```

Notes:
- `$where` is built from code, never from input; only values are bound.
- `LIMIT`/`OFFSET` placeholders need native prepares (Laravel's default for MySQL, `PDO::ATTR_EMULATE_PREPARES => false`). If you ever hit a quoting error there, bind them with `PDO::PARAM_INT` explicitly.
- Wrap paginated results in `Illuminate\Pagination\LengthAwarePaginator` in the controller or a small mapper, so the Inertia page gets the same pagination shape as products.
- Joins include soft-deleted products on purpose: history must show what was bought even if the product was later removed.
