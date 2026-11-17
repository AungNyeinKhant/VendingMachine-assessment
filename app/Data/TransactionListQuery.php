<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Normalized list-query input for transactions. Like ProductListQuery,
 * user input never reaches the repository unvalidated.
 */
final readonly class TransactionListQuery
{
    public const PER_PAGE = [10, 25, 50];

    public function __construct(
        public int $perPage = 25,
        public int $page = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $perPage = in_array((int) ($input['per_page'] ?? 0), self::PER_PAGE, true) ? (int) $input['per_page'] : 25;
        $page = max(1, (int) ($input['page'] ?? 1));

        return new self($perPage, $page);
    }
}
