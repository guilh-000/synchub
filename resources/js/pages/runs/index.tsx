import { Head, Link } from '@inertiajs/react';
import { RunsTable } from '@/components/sync/runs-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index } from '@/routes/runs';
import type { Paginated, SyncRun, SyncRunStatus } from '@/types';

type Props = {
    runs: Paginated<SyncRun>;
    filters: { status: SyncRunStatus | null };
};

const statusFilters: { value: SyncRunStatus | null; label: string }[] = [
    { value: null, label: 'All' },
    { value: 'succeeded', label: 'Succeeded' },
    { value: 'partial', label: 'Partial' },
    { value: 'failed', label: 'Failed' },
    { value: 'running', label: 'Running' },
];

export default function RunsIndex({ runs, filters }: Props) {
    return (
        <>
            <Head title="Runs" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                        <CardTitle>Runs</CardTitle>
                        <nav className="flex flex-wrap gap-1">
                            {statusFilters.map(({ value, label }) => (
                                <Link
                                    key={label}
                                    href={index({
                                        query: value ? { status: value } : {},
                                    })}
                                    preserveScroll
                                    className={cn(
                                        'rounded-md px-3 py-1 text-sm',
                                        filters.status === value
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:bg-muted',
                                    )}
                                >
                                    {label}
                                </Link>
                            ))}
                        </nav>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <RunsTable runs={runs.data} />

                        {runs.meta.last_page > 1 && (
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span>
                                    {runs.meta.from}–{runs.meta.to} of{' '}
                                    {runs.meta.total}
                                </span>
                                <div className="flex gap-2">
                                    <PageButton href={runs.links.prev}>
                                        Previous
                                    </PageButton>
                                    <PageButton href={runs.links.next}>
                                        Next
                                    </PageButton>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function PageButton({
    href,
    children,
}: {
    href: string | null;
    children: string;
}) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll>
                {children}
            </Link>
        </Button>
    );
}

RunsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Runs', href: index() },
    ],
};
