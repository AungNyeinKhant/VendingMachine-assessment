<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\ConnectionInterface;
use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper (assessment Task 2 — "a connection class").
 *
 * Borrows the PDO handle from Laravel's connection instead of opening a second
 * one: credentials live in one place (config/database.php) and raw statements
 * run inside the same DB transaction as Eloquent queries. Prepared statements
 * only — values are always bound, never interpolated.
 */
class PdoConnection
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    public function pdo(): PDO
    {
        $pdo = $this->connection->getPdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param  array<string, scalar|null>  $params
     */
    public function insert(string $sql, array $params): int
    {
        $this->run($sql, $params);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return int affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * @param  array<string, scalar|null>  $params
     */
    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}
