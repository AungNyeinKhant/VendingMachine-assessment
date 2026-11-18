<?php

declare(strict_types=1);

namespace App\Repositories\Pdo;

use App\Database\PdoConnection;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * Raw-PDO user queries (assessment Task 2). Rows are turned into User models
 * so the rest of the app (JWT, permissions) works with them as usual.
 */
class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PdoConnection $db) {}

    public function findById(int $id): ?User
    {
        return $this->toUser($this->db->selectOne('SELECT * FROM users WHERE id = :id', ['id' => $id]));
    }

    public function findByEmail(string $email): ?User
    {
        return $this->toUser($this->db->selectOne('SELECT * FROM users WHERE email = :email', ['email' => $email]));
    }

    public function create(array $data): User
    {
        $now = now()->toDateTimeString();

        $id = $this->db->insert(
            'INSERT INTO users (name, email, password, created_at, updated_at)
             VALUES (:name, :email, :password, :created_at, :updated_at)',
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        /** @var User */
        return $this->findById($id);
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private function toUser(?array $row): ?User
    {
        return $row === null ? null : (new User)->newFromBuilder($row);
    }
}
