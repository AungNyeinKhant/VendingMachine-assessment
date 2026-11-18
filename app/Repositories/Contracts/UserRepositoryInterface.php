<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * Insert a user and return it. The password must already be hashed.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data): User;
}
