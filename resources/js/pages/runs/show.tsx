import { Form, Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useEffect } from 'react';
import { RunCounters } from '@/components/sync/run-counters';
import { RunStatusBadge } from '@/components/sync/run-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dateTime, duration } from '@/lib/format';
import { dashboard } from '@/routes';
import { index, retry, show } from '@/routes/runs';
import type { SyncRun } from '@/types';

const syncTitles: Record<string, string> = {
    products: 'Products',
    stock: 'Stock',
    orders: 'Orders',
};

const itemLabels: Record<string, string> = {
    products: 'ERP code',
    stock: 'ERP code',
    orders: 'Store order',
};

const triggers: Record<SyncRun['trigger'], string> = {
    schedule: 'the schedule',
    manual: 'the dashboard',
    console: 'the console',
};

export default function RunsShow({ run }: { run: SyncRun }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Runs', href: index() },
            { title: `#${run.id}`, href: show(run.id) },
        ],
    });

    const { stop } = usePoll(
        3000,
        { only: ['run'] },
        { autoStart: run.status === 'running' },
    );

    // Follow a run in progress; stop once it has finished.
    useEffect(() => {
        if (run.status !== 'running') {
            stop();
        }
    }, [run.status, stop]);

    const failures = run.failures ?? [];

    return (
        <>
            <Head title={`Run #${run.id}`} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1.5">
                            <CardTitle className="flex items-center gap-2">
                                {syncTitles[run.sync] ?? run.sync} sync #
                                {run.id}
                                <RunStatusBadge status={run.status} />
                            </CardTitle>
                            <CardDescription>
                                Started {dateTime(run.started_at)} by{' '}
                                {triggers[run.trigger]}
                                {run.full && ' · full comparison'}
                                {' · '}
                                {duration(run.duration_seconds)}
                            </CardDescription>
                            {run.retry_of_id !== null && (
                                <p className="text-sm text-muted-foreground">
                                    Retry of the failed items of{' '}
                                    <Link
                                        href={show(run.retry_of_id)}
                                        className="font-medium text-foreground hover:underline"
                                    >
                                        #{run.retry_of_id}
                                    </Link>
                                </p>
                            )}
                            {(run.retries ?? []).length > 0 && (
                                <p className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    Retried in
                                    {run.retries?.map((retryRun) => (
                                        <Link
                                            key={retryRun.id}
                                            href={show(retryRun.id)}
                                            className="inline-flex items-center gap-1 font-medium text-foreground hover:underline"
                                        >
                                            #{retryRun.id}
                                            <RunStatusBadge
                                                status={retryRun.status}
                                            />
                                        </Link>
                                    ))}
                                </p>
                            )}
                        </div>
                        {failures.length > 0 && run.status !== 'running' && (
                            <Form
                                {...retry.form(run.id)}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <RotateCcw />
                                        Retry {failures.length} failed
                                    </Button>
                                )}
                            </Form>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <RunCounters run={run} />
                        {run.error && (
                            <div className="rounded-md bg-red-500/10 px-3 py-2 text-sm text-red-700 dark:text-red-300">
                                <p className="font-medium">
                                    The run stopped before the end
                                </p>
                                <p className="mt-1 font-mono text-xs break-all">
                                    {run.error}
                                </p>
                                <p className="mt-2 text-xs">
                                    Batches accepted before the error are kept;
                                    the next run resumes from the last one.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Rejected items</CardTitle>
                        <CardDescription>
                            Items that could not be synced. The rest of the run
                            went through.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {failures.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Nothing was rejected.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs text-muted-foreground">
                                    <tr className="border-b">
                                        <th className="py-2 pr-4 font-medium">
                                            {itemLabels[run.sync] ?? 'Item'}
                                        </th>
                                        <th className="py-2 font-medium">
                                            Reason
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {failures.map((failure) => (
                                        <tr
                                            key={failure.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-2 pr-4 font-mono">
                                                {failure.item_key}
                                            </td>
                                            <td className="py-2">
                                                {failure.message}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
