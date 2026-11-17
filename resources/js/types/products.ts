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

/** Paginated product list as sent by Laravel's paginator over Inertia. */
export type PaginatedProducts = {
    data: Product[];
    links: PaginatorLink[];
    meta?: PaginatorMeta;
};

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
