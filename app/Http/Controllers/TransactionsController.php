<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\TransactionListQuery;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class TransactionsController extends Controller
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
    ) {}

    public function index(Request $request): Response
    {
        $query = TransactionListQuery::fromArray($request->query());

        // view-all holders see every purchase; everyone else only their own.
        $scope = $request->user()?->can('transactions.view-all')
            ? null
            : $request->user()?->id;

        $rows = $this->transactions->paginateForUser($scope, $query->perPage, $query->page);

        return Inertia::render('transactions/index', [
            'transactions' => $this->toPaginator($rows, $query, $request),
            'canViewAll' => $scope === null,
        ]);
    }

    /**
     * Shape the raw-PDO rows as a paginator so the page can reuse the same
     * meta-based pagination component pattern as products.
     *
     * @param  array{data: list<array<string, mixed>>, total: int}  $rows
     */
    private function toPaginator(array $rows, TransactionListQuery $query, Request $request): LengthAwarePaginator
    {
        $paginator = new LengthAwarePaginator($rows['data'], $rows['total'], $query->perPage, $query->page);
        $paginator->withPath('/transactions')->appends($request->query());

        return $paginator;
    }
}
