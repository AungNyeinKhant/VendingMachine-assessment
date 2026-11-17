/**
 * Client-side validation mirroring the server FormRequest rules
 * (assessment Task 13). Server validation remains authoritative; both
 * error sets are shown through the kit's InputError component.
 */

export type FormErrors = Record<string, string>;

type ProductFormData = {
    name: string;
    price: string;
    quantity_available: string | number;
};

type PurchaseFormData = {
    quantity: string | number;
    amount_paid: string;
};

const DECIMAL_PATTERN = /^\d+(\.\d{1,3})?$/;

function isBlank(value: unknown): boolean {
    return value === null || value === undefined || String(value).trim() === '';
}

/** Mirrors StoreProductRequest / UpdateProductRequest. */
export function validateProduct(data: ProductFormData): FormErrors {
    const errors: FormErrors = {};

    if (isBlank(data.name)) {
        errors.name = 'The name field is required.';
    } else if (String(data.name).length > 100) {
        errors.name = 'The name may not be greater than 100 characters.';
    }

    if (isBlank(data.price)) {
        errors.price = 'The price field is required.';
    } else {
        const price = String(data.price).trim();
        const parsed = Number.parseFloat(price);

        if (!DECIMAL_PATTERN.test(price) || Number.isNaN(parsed)) {
            errors.price = 'The price must be a number with up to 3 decimals.';
        } else if (parsed <= 0) {
            errors.price = 'The price must be greater than 0.';
        } else if (parsed > 99999.999) {
            errors.price = 'The price may not be greater than 99999.999.';
        }
    }

    const quantity = String(data.quantity_available ?? '').trim();

    if (isBlank(data.quantity_available)) {
        errors.quantity_available = 'The quantity available field is required.';
    } else if (!/^\d+$/.test(quantity)) {
        errors.quantity_available = 'The quantity available must be an integer.';
    } else if (Number.parseInt(quantity, 10) < 0) {
        errors.quantity_available = 'The quantity available must be at least 0.';
    }

    return errors;
}

/** Mirrors PurchaseRequest. */
export function validatePurchase(data: PurchaseFormData): FormErrors {
    const errors: FormErrors = {};

    const quantity = String(data.quantity ?? '').trim();

    if (isBlank(data.quantity)) {
        errors.quantity = 'The quantity field is required.';
    } else if (!/^\d+$/.test(quantity) || Number.parseInt(quantity, 10) < 1) {
        errors.quantity = 'The quantity must be at least 1.';
    }

    if (isBlank(data.amount_paid)) {
        errors.amount_paid = 'The amount inserted field is required.';
    } else {
        const amount = String(data.amount_paid).trim();
        const parsed = Number.parseFloat(amount);

        if (!DECIMAL_PATTERN.test(amount) || Number.isNaN(parsed)) {
            errors.amount_paid = 'The amount inserted must be a number with up to 3 decimals.';
        } else if (parsed <= 0) {
            errors.amount_paid = 'The amount inserted must be greater than 0.';
        }
    }

    return errors;
}

export function hasErrors(errors: FormErrors): boolean {
    return Object.keys(errors).length > 0;
}
