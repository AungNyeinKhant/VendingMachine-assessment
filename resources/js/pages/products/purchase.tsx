import { Head, useForm, usePage } from '@inertiajs/react';
import { setLayoutProps } from '@inertiajs/react';
import { Banknote, Coins, ShoppingCart } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { formatPrice, previewChange, previewTotal } from '@/lib/money';
import { hasErrors, validatePurchase } from '@/lib/validation';
import { index as productsIndex, purchase } from '@/routes/products';
import { store as purchaseStore } from '@/routes/products/purchase';
import type { Auth, Product, Receipt } from '@/types';

type PurchasePageProps = {
    product: Product;
    auth: Auth;
};

const QUICK_AMOUNTS = [1, 5, 10, 20];

type PurchaseFormData = {
    quantity: string;
    amount_paid: string;
};

export default function PurchasePage() {
    const { product, auth } = usePage<PurchasePageProps>().props;
    const receipt = usePage<{ flash: { receipt?: Receipt } }>().flash.receipt;

    const { data, setData, post, processing, errors } =
        useForm<PurchaseFormData>({
            quantity: '1',
            amount_paid: '',
        });

    const [clientErrors, setClientErrors] = useState<Record<string, string>>(
        {},
    );

    setLayoutProps({
        breadcrumbs: [
            { title: 'Products', href: productsIndex() },
            {
                title: `Buy ${product.name}`,
                href: purchase.url({ product: product.slug }),
            },
        ],
    });

    const quantity = Math.max(1, Number.parseInt(data.quantity, 10) || 1);
    const canPurchase = auth.permissions.includes('products.purchase');

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const validationErrors = validatePurchase(data);
        setClientErrors(validationErrors);

        if (hasErrors(validationErrors)) {
            return;
        }

        post(purchaseStore.url({ product: product.slug }));
    }

    return (
        <>
            <Head title={`Buy ${product.name}`} />

            <div className="max-w-lg space-y-6">
                <Heading
                    title={`Buy ${product.name}`}
                    description="Insert money, choose a quantity and the machine returns the change"
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <span>{product.name}</span>
                            <span className="font-normal">
                                {formatPrice(product.price)} each
                            </span>
                        </CardTitle>
                        <CardDescription>
                            In stock:{' '}
                            <Badge
                                variant={
                                    product.quantity_available > 0
                                        ? 'secondary'
                                        : 'destructive'
                                }
                            >
                                {product.quantity_available}
                            </Badge>
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="space-y-6">
                        <form
                            onSubmit={submit}
                            className="space-y-6"
                            noValidate
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="quantity">Quantity</Label>

                                <Input
                                    id="quantity"
                                    name="quantity"
                                    type="number"
                                    step="1"
                                    min="1"
                                    max={product.quantity_available}
                                    value={data.quantity}
                                    onChange={(event) =>
                                        setData('quantity', event.target.value)
                                    }
                                    required
                                />

                                <InputError
                                    className="mt-2"
                                    message={
                                        clientErrors.quantity ?? errors.quantity
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="amount_paid"
                                    className="flex items-center gap-1"
                                >
                                    <Banknote className="size-4" />
                                    Amount inserted (USD)
                                </Label>

                                <Input
                                    id="amount_paid"
                                    name="amount_paid"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    value={data.amount_paid}
                                    onChange={(event) =>
                                        setData(
                                            'amount_paid',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    placeholder="e.g. 20"
                                />

                                <InputError
                                    className="mt-2"
                                    message={
                                        clientErrors.amount_paid ??
                                        errors.amount_paid
                                    }
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                {QUICK_AMOUNTS.map((amount) => (
                                    <Button
                                        key={amount}
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setData(
                                                'amount_paid',
                                                amount.toFixed(3),
                                            )
                                        }
                                        data-test={`quick-${amount}`}
                                    >
                                        ${amount}
                                    </Button>
                                ))}
                            </div>

                            {/* Display-only preview; the server is authoritative. */}
                            <div className="grid gap-2 rounded-lg border bg-muted/40 p-4 text-sm">
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1 text-muted-foreground">
                                        <Coins className="size-4" />
                                        Total
                                    </span>
                                    <span data-test="preview-total">
                                        {previewTotal(product.price, quantity)}
                                    </span>
                                </div>
                                <Separator />
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Change
                                    </span>
                                    <span data-test="preview-change">
                                        {previewChange(
                                            data.amount_paid,
                                            (
                                                Number.parseFloat(
                                                    product.price,
                                                ) * quantity
                                            ).toFixed(3),
                                        )}
                                    </span>
                                </div>
                            </div>

                            {canPurchase && (
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="purchase-submit"
                                >
                                    <ShoppingCart className="size-4" />
                                    Buy {quantity > 1 ? `${quantity} ` : ''}
                                    {product.name}
                                </Button>
                            )}
                        </form>

                        {receipt && <ReceiptCard receipt={receipt} />}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ReceiptCard({ receipt }: { receipt: Receipt }) {
    return (
        <div
            className="rounded-lg border p-4 text-sm"
            data-test="purchase-receipt"
        >
            <p className="mb-3 font-medium">
                Receipt #{receipt.transaction_id}
            </p>

            <dl className="grid gap-1.5">
                {[
                    ['Product', receipt.product_name],
                    ['Quantity', String(receipt.quantity)],
                    ['Unit price', formatPrice(receipt.unit_price)],
                    ['Total', formatPrice(receipt.total_price)],
                    ['Inserted', formatPrice(receipt.amount_paid)],
                    ['Change', formatPrice(receipt.change_given)],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="flex items-center justify-between"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd>{value}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
