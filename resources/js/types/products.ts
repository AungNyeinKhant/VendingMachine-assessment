export type Product = {
    id: number;
    name: string;
    slug: string;
    /** DECIMAL(10,3) as string, e.g. "6.885" — never a float */
    price: string;
    quantity_available: number;
    created_at: string;
    updated_at: string;
};

/**
 * A LengthAwarePaginator serialized by Laravel: the pagination fields sit at
 * the top level next to `data` (there is no `meta` wrapper — that shape only
 * exists for API Resources).
 */
export type Paginated<T> = PaginatorMeta & {
    data: T[];
    links: PaginatorLink[];
};

export type PaginatedProducts = Paginated<Product>;

export type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatorMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};

/** Query params accepted by the products listing (whitelisted server-side). */
export type ProductFilters = {
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    q: string | null;
};
