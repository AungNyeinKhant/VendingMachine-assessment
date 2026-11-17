import { Head, Link, router, usePage } from '@inertiajs/react';
import { setLayoutProps } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Pencil, Search, ShoppingBag, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatPrice } from '@/lib/money';
import { create, edit, index, purchase, show } from '@/routes/products';
import type { Auth, PaginatedProducts, Product, ProductFilters } from '@/types';

type ProductsIndexProps = {
    products: PaginatedProducts;
    filters: ProductFilters;
    auth: Auth;
};

const SORTABLE_COLUMNS: Array<{ label: string; sort: string }> = [
    { label: 'Name', sort: 'name' },
    { label: 'Price', sort: 'price' },
    { label: 'In stock', sort: 'quantity_available' },
    { label: 'Added', sort: 'created_at' },
];

export default function ProductsIndex() {
    const { products, filters, auth } = usePage<ProductsIndexProps>().props;
    const canManage = auth.permissions.includes('products.manage');
    const canPurchase = auth.permissions.includes('products.purchase');

    const [search, setSearch] = useState(filters.q ?? '');
    const lastSubmittedTerm = useRef<string>(filters.q ?? '');

    // Debounced search: submits ~300ms after typing stops, only when the term changed.
    useEffect(() => {
        const timer = setTimeout(() => {
            if (search === lastSubmittedTerm.current) {
                return;
            }
            lastSubmittedTerm.current = search;
            applyFilters({ q: search || undefined, page: undefined });
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    setLayoutProps({
        breadcrumbs: [{ title: 'Products', href: index() }],
    });

    function applyFilters(
        patch: Partial<{
            sort: string;
            direction: 'asc' | 'desc';
            per_page: number;
            q: string | undefined;
            page: number | undefined;
        }>,
    ) {
        router.get(
            index.url(),
            {
                sort: patch.sort ?? filters.sort,
                direction: patch.direction ?? filters.direction,
                per_page: patch.per_page ?? filters.per_page,
                q: patch.q !== undefined ? patch.q : (filters.q ?? undefined),
                page: patch.page ?? undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }

    function toggleSort(column: string) {
        applyFilters({
            sort: column,
            direction: filters.sort === column && filters.direction === 'asc' ? 'desc' : 'asc',
        });
    }

    function goToPage(page: number) {
        applyFilters({ page });
    }

    const meta = products.meta;

    return (
        <>
            <Head title="Products" />

            <div className="space-y-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Products"
                        description="All products in the vending machine"
                    />

                    {canManage && (
                        <Button asChild data-test="create-product-link">
                            <Link href={create()}>New product</Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative w-full max-w-xs">
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            className="pl-8"
                            placeholder="Search products…"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            data-test="product-search"
                        />
                    </div>

                    <Select
                        value={String(filters.per_page)}
                        onValueChange={(value) => applyFilters({ per_page: Number(value), page: undefined })}
                    >
                        <SelectTrigger className="w-36" data-test="per-page-select">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[5, 10, 25].map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size} per page
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm" data-test="products-table">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                {SORTABLE_COLUMNS.map((column) => (
                                    <th key={column.sort} className="px-4 py-3">
                                        <button
                                            type="button"
                                            className="flex items-center gap-1 hover:text-foreground"
                                            onClick={() => toggleSort(column.sort)}
                                            data-test={`sort-${column.sort}`}
                                        >
                                            {column.label}
                                            {filters.sort === column.sort &&
                                                (filters.direction === 'asc' ? (
                                                    <ChevronUp className="size-3.5" />
                                                ) : (
                                                    <ChevronDown className="size-3.5" />
                                                ))}
                                        </button>
                                    </th>
                                ))}
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.map((product: Product) => (
                                <tr key={product.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show.url({ product: product.slug })}
                                            className="font-medium hover:underline"
                                        >
                                            {product.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{formatPrice(product.price)}</td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={product.quantity_available > 0 ? 'secondary' : 'destructive'}
                                        >
                                            {product.quantity_available}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        {canPurchase && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                disabled={product.quantity_available < 1}
                                                className="mr-2"
                                                data-test={`buy-${product.slug}`}
                                            >
                                                <Link href={purchase.url({ product: product.slug })}>
                                                    <ShoppingBag className="size-4" />
                                                    Buy
                                                </Link>
                                            </Button>
                                        )}

                                        {canManage && (
                                            <div className="inline-flex gap-2">
                                                <Button asChild size="sm" variant="outline">
                                                    <Link
                                                        href={edit.url({ product: product.slug })}
                                                        aria-label={`Edit ${product.name}`}
                                                        data-test={`edit-${product.slug}`}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        if (confirm(`Delete ${product.name}?`)) {
                                                            void router.delete(
                                                                show.url({ product: product.slug }),
                                                            );
                                                        }
                                                    }}
                                                    aria-label={`Delete ${product.name}`}
                                                    data-test={`delete-${product.slug}`}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}

                            {products.data.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">
                                        No products found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {meta && meta.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {meta.from}–{meta.to} of {meta.total} products
                        </p>

                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={meta.current_page <= 1}
                                onClick={() => goToPage(meta.current_page - 1)}
                            >
                                Previous
                            </Button>

                            {Array.from({ length: meta.last_page }, (_, i) => i + 1)
                                .filter(
                                    (page) =>
                                        page === 1 ||
                                        page === meta.last_page ||
                                        Math.abs(page - meta.current_page) <= 1,
                                )
                                .map((page, position, pages) => (
                                    <span key={page} className="flex items-center gap-1">
                                        {position > 0 && page - pages[position - 1] > 1 && (
                                            <span className="px-1 text-muted-foreground">…</span>
                                        )}
                                        <Button
                                            variant={page === meta.current_page ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => goToPage(page)}
                                        >
                                            {page}
                                        </Button>
                                    </span>
                                ))}

                            <Button
                                variant="outline"
                                size="sm"
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => goToPage(meta.current_page + 1)}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
