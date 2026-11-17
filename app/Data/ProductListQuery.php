<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Normalized list-query input for products. User input never reaches
 * orderBy() directly — sort and direction are whitelisted here.
 */
final readonly class ProductListQuery
{
    public const SORTABLE = ['name', 'price', 'quantity_available', 'created_at'];

    public const PER_PAGE = [5, 10, 25];

    /**
     * @param  'asc'|'desc'  $direction
     */
    public function __construct(
        public string $sort = 'name',
        public string $direction = 'asc',
        public int $perPage = 10,
        public ?string $search = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $sort = in_array($input['sort'] ?? null, self::SORTABLE, true) ? (string) $input['sort'] : 'name';
        $direction = ($input['direction'] ?? null) === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) ($input['per_page'] ?? 0), self::PER_PAGE, true) ? (int) $input['per_page'] : 10;
        // ?q[]=x arrives as an array — ignore anything that isn't a plain string.
        $search = is_string($input['q'] ?? null) ? (trim($input['q']) ?: null) : null;

        return new self($sort, $direction, $perPage, $search);
    }

    /**
     * @return array{sort: string, direction: string, per_page: int, q: ?string}
     */
    public function toArray(): array
    {
        return [
            'sort' => $this->sort,
            'direction' => $this->direction,
            'per_page' => $this->perPage,
            'q' => $this->search,
        ];
    }
}
