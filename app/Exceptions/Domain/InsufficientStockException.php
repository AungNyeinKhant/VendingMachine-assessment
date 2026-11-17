<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

class InsufficientStockException extends DomainException
{
    public function __construct(public readonly int $available)
    {
        parent::__construct("Only {$available} left in stock.");
    }

    public function errorKey(): string
    {
        return 'quantity';
    }

    public function status(): int
    {
        return 409;
    }
}
