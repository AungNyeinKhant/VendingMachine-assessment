<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

class ProductUnavailableException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This product is no longer available.');
    }

    public function errorKey(): string
    {
        return 'product';
    }

    public function status(): int
    {
        return 409;
    }
}
