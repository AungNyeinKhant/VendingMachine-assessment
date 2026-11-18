<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Data\TransactionListQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
    ) {}

    /**
     * List transactions
     *
     * Users see their own purchases; holders of `transactions.view-all` see everyone's.
     *
     * @group Purchases
     *
     * @authenticated
     *
     * @response 200 {"data": [{"id": 1, "product_name": "Pepsi", "quantity": 3, "unit_price": "6.885", "total_price": "20.655", "amount_paid": "25.000", "change_given": "4.345", "created_at": "2026-11-18T03:46:09+00:00"}]}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TransactionListQuery::fromArray($request->query());

        // view-all holders see every purchase; everyone else only their own.
        $scope = $request->user()?->can('transactions.view-all')
            ? null
            : $request->user()?->id;

        $result = $this->transactions->paginateForUser($scope, $query->perPage, $query->page);

        return TransactionResource::collection($result['data']);
    }
}
