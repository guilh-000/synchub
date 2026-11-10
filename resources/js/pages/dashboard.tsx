import { Deferred, Form, Head, Link, usePoll } from '@inertiajs/react';
import { ArrowRight, Database, Play, Store } from 'lucide-react';
import { RunCounters } from '@/components/sync/run-counters';
import { RunStatusBadge } from '@/components/sync/run-status-badge';
import { RunsTable } from '@/components/sync/runs-table';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { dateTime, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as runsIndex, show } from '@/routes/runs';
import { run as runProducts } from '@/routes/syncs/products';
import type { HealthCheck, SyncOverview, SyncRun } from '@/types';

type Props = {
    syncs: SyncOverview[];
    recentRuns: SyncRun[];
    health?: { erp: HealthCheck; store: HealthCheck };
};

export default function Dashboard({ syncs, recentRuns, health }: Props) {
    // Cheap queries: keeps run status and counters live without a refresh.
    usePoll(4000, { only: ['syncs', 'recentRuns'] });

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <HealthCard
                        title="Legacy ERP"
                        description="MySQL 5.7 · read and write over SQL"
                        icon={Database}
                        check={health?.erp}
                    />
                    <HealthCard
                        title="Store"
                        description="WooCommerce · REST API"
                        icon={Store}
                        check={health?.store}
                    />
                </div>

                {syncs.map((sync) => (
                    <SyncCard key={sync.key} sync={sync} />
                ))}

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div className="space-y-1.5">
                            <CardTitle>Recent runs</CardTitle>
                            <CardDescription>
                                Every execution, scheduled or started by hand.
                            </CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={runsIndex()} prefetch>
                                All runs <ArrowRight />
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <RunsTable runs={recentRuns} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function HealthCard({
    title,
    description,
    icon: Icon,
    check,
}: {
    title: string;
    description: string;
    icon: typeof Database;
    check?: HealthCheck;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start gap-3">
                <div className="rounded-lg bg-muted p-2">
                    <Icon className="size-5" />
                </div>
                <div className="flex-1 space-y-1.5">
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <Deferred
                    data="health"
                    fallback={<Skeleton className="h-5 w-24" />}
                >
                    {check ? (
                        <div className="text-right">
                            <span
                                className={cn(
                                    'inline-flex items-center gap-1.5 text-sm font-medium',
                                    check.ok
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-red-700 dark:text-red-300',
                                )}
                            >
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        check.ok
                                            ? 'bg-emerald-500'
                                            : 'bg-red-500',
                                    )}
                                />
                                {check.ok ? 'Reachable' : 'Unreachable'}
                            </span>
                            <p
                                className="max-w-56 truncate text-xs text-muted-foreground"
                                title={check.detail}
                            >
                                {check.detail}
                            </p>
                        </div>
                    ) : null}
                </Deferred>
            </CardHeader>
        </Card>
    );
}

function SyncCard({ sync }: { sync: SyncOverview }) {
    const lastRun = sync.last_run;

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle className="flex items-center gap-2">
                        {sync.title}
                        <span className="text-sm font-normal text-muted-foreground">
                            {sync.direction}
                        </span>
                    </CardTitle>
                    <CardDescription>
                        {sync.schedule} · {sync.mapped} mapped
                        {sync.watermark && (
                            <> · up to date until {dateTime(sync.watermark)}</>
                        )}
                    </CardDescription>
                </div>
                <Form
                    {...runProducts.form()}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button disabled={processing || sync.running}>
                            {processing || sync.running ? (
                                <Spinner />
                            ) : (
                                <Play />
                            )}
                            {sync.running ? 'Running…' : 'Run now'}
                        </Button>
                    )}
                </Form>
            </CardHeader>
            <CardContent className="space-y-3">
                {lastRun ? (
                    <>
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Last run
                            </span>
                            <Link
                                href={show(lastRun.id)}
                                className="font-medium hover:underline"
                            >
                                #{lastRun.id}
                            </Link>
                            <RunStatusBadge status={lastRun.status} />
                            <span className="text-muted-foreground">
                                {timeAgo(lastRun.started_at)}
                            </span>
                        </div>
                        <RunCounters run={lastRun} />
                        {lastRun.error && (
                            <p className="rounded-md bg-red-500/10 px-3 py-2 text-sm text-red-700 dark:text-red-300">
                                {lastRun.error}
                            </p>
                        )}
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Never run. Press “Run now” or wait for the schedule.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
