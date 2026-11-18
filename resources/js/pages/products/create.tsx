import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { create, store } from '@/routes/products';
import ProductForm from './partials/product-form';

export default function CreateProduct() {
    return (
        <>
            <Head title="New product" />

            <div className="space-y-6">
                <Heading
                    title="New product"
                    description="Add a product to the vending machine"
                />

                <ProductForm
                    submitUrl={store.url()}
                    submitMethod="post"
                    submitLabel="Create product"
                />
            </div>
        </>
    );
}

CreateProduct.layout = {
    breadcrumbs: [
        { title: 'Products', href: '/products' },
        { title: 'New', href: create() },
    ],
};
