import { Head, usePage } from '@inertiajs/react';
import { setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { formatPrice } from '@/lib/money';
import { index as transactionsIndex } from '@/routes/transactions';
import type { PaginatedTransactions } from '@/types';

type TransactionsIndexProps = {
    transactions: PaginatedTransactions;
    canViewAll: boolean;
};

export default function TransactionsIndex() {
    const { transactions, canViewAll } = usePage<TransactionsIndexProps>().props;
    const meta = transactions.meta;

    setLayoutProps({
        breadcrumbs: [{ title: 'Transactions', href: transactionsIndex() }],
    });

    return (
        <>
            <Head title="Transactions" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Transactions"
                        description={
                            canViewAll
                                ? 'Every purchase made on this machine'
                                : 'Your purchase history'
                        }
                    />

                    {canViewAll && <Badge variant="secondary">view all</Badge>}
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm" data-test="transactions-table">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                {canViewAll && <th className="px-4 py-3">User</th>}
                                <th className="px-4 py-3">Product</th>
                                <th className="px-4 py-3">Quantity</th>
                                <th className="px-4 py-3">Unit price</th>
                                <th className="px-4 py-3">Total</th>
                                <th className="px-4 py-3">Inserted</th>
                                <th className="px-4 py-3">Change</th>
                                <th className="px-4 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.data.map((transaction) => (
                                <tr key={transaction.id} className="border-t">
                                    {canViewAll && (
                                        <td className="px-4 py-3">{transaction.user_name}</td>
                                    )}
                                    <td className="px-4 py-3 font-medium">{transaction.product_name}</td>
                                    <td className="px-4 py-3">{transaction.quantity}</td>
                                    <td className="px-4 py-3">{formatPrice(transaction.unit_price)}</td>
                                    <td className="px-4 py-3">{formatPrice(transaction.total_price)}</td>
                                    <td className="px-4 py-3">{formatPrice(transaction.amount_paid)}</td>
                                    <td className="px-4 py-3">{formatPrice(transaction.change_given)}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                        {transaction.created_at}
                                    </td>
                                </tr>
                            ))}

                            {transactions.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={canViewAll ? 8 : 7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No transactions yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
