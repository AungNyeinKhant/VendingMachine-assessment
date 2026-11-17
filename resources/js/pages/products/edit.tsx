import { Head, usePage } from '@inertiajs/react';
import { setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index, edit } from '@/routes/products';
import type { Product } from '@/types';
import ProductForm from './partials/product-form';

type EditProductProps = {
    product: Product;
};

export default function EditProduct() {
    const { product } = usePage<EditProductProps>().props;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Products', href: index() },
            {
                title: `Edit ${product.name}`,
                href: edit.url({ product: product.slug }),
            },
        ],
    });

    return (
        <>
            <Head title={`Edit ${product.name}`} />

            <div className="space-y-6">
                <Heading
                    title={`Edit ${product.name}`}
                    description="Update the product details"
                />

                <ProductForm
                    product={product}
                    submitUrl={edit.url({ product: product.slug })}
                    submitMethod="put"
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}
