<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface TransactionRepositoryInterface
{
    /**
     * Log one purchase and return its id. Money values are decimal strings.
     *
     * @param  array{user_id: int, product_id: int, quantity: int, unit_price: string, total_price: string, amount_paid: string, change_given: string}  $data
     */
    public function create(array $data): int;

    /**
     * Paginated purchase history, newest first, with product and user names.
     * A null $userId returns every transaction (permission: transactions.view-all).
     *
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public function paginateForUser(?int $userId, int $perPage, int $page): array;
}
