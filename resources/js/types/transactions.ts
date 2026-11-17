import type { PaginatorMeta } from './products';

export type Transaction = {
    id: number;
    user_id: number;
    product_id: number;
    quantity: number;
    /** DECIMAL(10,3)/DECIMAL(12,3) as strings — never floats */
    unit_price: string;
    total_price: string;
    amount_paid: string;
    change_given: string;
    created_at: string;
    /** Joined by the raw-PDO repository. */
    product_name: string;
    user_name: string;
};

export type PaginatedTransactions = {
    data: Transaction[];
    meta?: PaginatorMeta;
};
