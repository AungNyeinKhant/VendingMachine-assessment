import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Product } from '@/types';
import { hasErrors, validateProduct, type FormErrors } from '@/lib/validation';

type ProductFormProps = {
    /** Omit to create; provide to edit. */
    product?: Product;
    submitUrl: string;
    submitMethod: 'post' | 'put';
    submitLabel: string;
};

type ProductFormData = {
    name: string;
    price: string;
    quantity_available: string;
};

export default function ProductForm({
    product,
    submitUrl,
    submitMethod,
    submitLabel,
}: ProductFormProps) {
    const { data, setData, post, put, processing, errors } =
        useForm<ProductFormData>({
            name: product?.name ?? '',
            price: product?.price ?? '',
            quantity_available: String(product?.quantity_available ?? ''),
        });

    const [clientErrors, setClientErrors] = useState<FormErrors>({});

    function submit(event: React.FormEvent) {
        event.preventDefault();

        // Task 13: client-side validation mirrors the FormRequest rules;
        // server errors arrive in `errors` and are shown alongside.
        const validationErrors = validateProduct(data);
        setClientErrors(validationErrors);

        if (hasErrors(validationErrors)) {
            return;
        }

        if (submitMethod === 'put') {
            put(submitUrl);
        } else {
            post(submitUrl);
        }
    }

    return (
        <form onSubmit={submit} className="max-w-md space-y-6" noValidate>
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>

                <Input
                    id="name"
                    name="name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    maxLength={100}
                    required
                    placeholder="e.g. Coke"
                />

                <InputError
                    className="mt-2"
                    message={clientErrors.name ?? errors.name}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="price">Price (USD)</Label>

                <Input
                    id="price"
                    name="price"
                    type="number"
                    step="0.001"
                    min="0.001"
                    max="99999.999"
                    value={data.price}
                    onChange={(event) => setData('price', event.target.value)}
                    required
                    placeholder="e.g. 6.885"
                />

                <InputError
                    className="mt-2"
                    message={clientErrors.price ?? errors.price}
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="quantity_available">Quantity available</Label>

                <Input
                    id="quantity_available"
                    name="quantity_available"
                    type="number"
                    step="1"
                    min="0"
                    value={data.quantity_available}
                    onChange={(event) =>
                        setData('quantity_available', event.target.value)
                    }
                    required
                    placeholder="e.g. 10"
                />

                <InputError
                    className="mt-2"
                    message={
                        clientErrors.quantity_available ??
                        errors.quantity_available
                    }
                />
            </div>

            <div className="flex items-center gap-4">
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="product-form-submit"
                >
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
