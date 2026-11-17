<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

class InsufficientFundsException extends DomainException
{
    public function __construct(public readonly string $required)
    {
        parent::__construct("You need to insert at least {$required} USD.");
    }

    public function errorKey(): string
    {
        return 'amount_paid';
    }

    public function status(): int
    {
        return 422;
    }
}
