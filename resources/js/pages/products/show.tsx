import { Head, Link, usePage } from '@inertiajs/react';
import { setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatPrice } from '@/lib/money';
import { edit, index, show } from '@/routes/products';
import type { Auth, Product } from '@/types';

type ShowProductProps = {
    product: Product;
    auth: Auth;
};

export default function ShowProduct() {
    const { product, auth } = usePage<ShowProductProps>().props;
    const canManage = auth.permissions.includes('products.manage');

    setLayoutProps({
        breadcrumbs: [
            { title: 'Products', href: index() },
            { title: product.name, href: show.url({ product: product.slug }) },
        ],
    });

    return (
        <>
            <Head title={product.name} />

            <div className="space-y-6">
                <Heading
                    title={product.name}
                    description={`Vending machine product details for ${product.name}`}
                />

                <Card className="max-w-md">
                    <CardHeader>
                        <CardTitle>{product.name}</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-muted-foreground">
                                Price
                            </span>
                            <span className="font-medium">
                                {formatPrice(product.price)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-muted-foreground">
                                In stock
                            </span>
                            <Badge
                                variant={
                                    product.quantity_available > 0
                                        ? 'secondary'
                                        : 'destructive'
                                }
                            >
                                {product.quantity_available}
                            </Badge>
                        </div>

                        <div className="flex gap-2 pt-2">
                            {canManage && (
                                <Button asChild variant="outline">
                                    <Link
                                        href={edit.url({
                                            product: product.slug,
                                        })}
                                    >
                                        Edit
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
