/**
 * Money helpers. All money travels as strings ("6.885") — never floats.
 * Frontend math is display-only; the server is authoritative.
 */

/** Format a decimal string as USD with min 2 / max 3 decimals: "6.885" -> "6.885", "3.990" -> "3.99", "0.500" -> "0.50". */
export function formatPrice(value: string | number): string {
    const number = typeof value === 'string' ? Number.parseFloat(value) : value;

    if (Number.isNaN(number)) {
        return '0.00';
    }

    return number.toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 3,
    });
}

/** Total preview for a purchase: price × quantity (display only). */
export function previewTotal(price: string, quantity: number): string {
    return formatPrice((Number.parseFloat(price) || 0) * quantity);
}

/** Change preview: amount inserted − total (display only, may be negative). */
export function previewChange(amountPaid: string, total: string): string {
    return formatPrice((Number.parseFloat(amountPaid) || 0) - (Number.parseFloat(total) || 0));
}
