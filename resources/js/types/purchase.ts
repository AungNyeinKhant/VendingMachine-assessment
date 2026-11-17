/** Purchase receipt, flashed by the server after a successful purchase. */
export type Receipt = {
    transaction_id: number;
    product_name: string;
    quantity: number;
    unit_price: string;
    total_price: string;
    amount_paid: string;
    change_given: string;
};
