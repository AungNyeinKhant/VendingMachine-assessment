<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Database\PdoConnection;
use App\Repositories\Contracts\TransactionRepositoryInterface;

/**
 * Raw-PDO implementation of the transaction log (assessment Task 2 — "perform
 * CRUD operations ... using PDO" for transactions; products stay on Eloquent
 * via the Eloquent product repository).
 *
 * The PDO handle is shared with Laravel's connection, so an insert performed
 * here participates in the same DB transaction that PurchaseService opened —
 * a purchase is committed or rolled back as one unit.
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
                'unit_price' => $data['unit_price'], // strings — DECIMAL precision preserved
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
