<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Pdo\PdoTransactionRepository;
use App\Repositories\Pdo\PdoUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProductRepositoryInterface::class => EloquentProductRepository::class,
        TransactionRepositoryInterface::class => PdoTransactionRepository::class,
        UserRepositoryInterface::class => PdoUserRepository::class,
    ];
}
