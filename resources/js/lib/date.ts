/**
 * Formats a server timestamp in the viewer's local time zone.
 * Accepts ISO 8601 ("2026-11-18T03:46:09.000000Z", from Eloquent) and
 * MySQL DATETIME ("2026-11-18 03:46:09", from the raw-PDO repository),
 * both stored in UTC.
 */
export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const iso = value.includes('T') ? value : `${value.replace(' ', 'T')}Z`;
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
