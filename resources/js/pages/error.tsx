import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index as productsIndex } from '@/routes/products';

type ErrorPageProps = {
    status: number;
};

const MESSAGES: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Forbidden',
        description: "You don't have permission to do that.",
    },
    404: {
        title: 'Page not found',
        description: "The page you're looking for doesn't exist.",
    },
    419: {
        title: 'Page expired',
        description: 'Your session expired. Please refresh and try again.',
    },
    500: {
        title: 'Server error',
        description: 'Something went wrong on our side.',
    },
    503: {
        title: 'Service unavailable',
        description: "We're doing some maintenance. Please check back soon.",
    },
};

/**
 * Rendered for 403/404/419/500/503 web responses outside local/testing
 * (see bootstrap/app.php), so errors keep the app's look instead of
 * Laravel's plain default page.
 */
export default function ErrorPage({ status }: ErrorPageProps) {
    const message = MESSAGES[status] ?? MESSAGES[500];

    return (
        <>
            <Head title={message.title} />

            <div className="flex min-h-svh flex-col items-center justify-center gap-4 bg-background p-6 text-center">
                <p className="text-6xl font-semibold text-muted-foreground">
                    {status}
                </p>
                <h1 className="text-xl font-medium">{message.title}</h1>
                <p className="max-w-sm text-sm text-muted-foreground">
                    {message.description}
                </p>
                <Button asChild variant="outline">
                    <Link href={productsIndex()}>Back to products</Link>
                </Button>
            </div>
        </>
    );
}
