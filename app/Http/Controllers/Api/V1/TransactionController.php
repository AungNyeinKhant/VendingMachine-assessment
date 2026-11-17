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
