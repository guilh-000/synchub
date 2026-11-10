import { Link } from '@inertiajs/react';
import { RunStatusBadge } from '@/components/sync/run-status-badge';
import { duration, timeAgo } from '@/lib/format';
import { show } from '@/routes/runs';
import type { SyncRun } from '@/types';

const triggers: Record<SyncRun['trigger'], string> = {
    schedule: 'Schedule',
    manual: 'Dashboard',
    console: 'Console',
};

export function RunsTable({ runs }: { runs: SyncRun[] }) {
    if (runs.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                No runs yet.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground">
                    <tr className="border-b">
                        <th className="py-2 pr-4 font-medium">Run</th>
                        <th className="py-2 pr-4 font-medium">Status</th>
                        <th className="py-2 pr-4 font-medium">Started</th>
                        <th className="py-2 pr-4 font-medium">Trigger</th>
                        <th className="py-2 pr-4 text-right font-medium">
                            Sent
                        </th>
                        <th className="py-2 pr-4 text-right font-medium">
                            Unchanged
                        </th>
                        <th className="py-2 pr-4 text-right font-medium">
                            Failed
                        </th>
                        <th className="py-2 text-right font-medium">
                            Duration
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {runs.map((run) => (
                        <tr
                            key={run.id}
                            className="border-b last:border-0 hover:bg-muted/40"
                        >
                            <td className="py-2 pr-4">
                                <Link
                                    href={show(run.id)}
                                    className="font-medium hover:underline"
                                    prefetch
                                >
                                    #{run.id}
                                </Link>
                                {run.full && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        full
                                    </span>
                                )}
                            </td>
                            <td className="py-2 pr-4">
                                <RunStatusBadge status={run.status} />
                            </td>
                            <td
                                className="py-2 pr-4 whitespace-nowrap"
                                title={run.started_at}
                            >
                                {timeAgo(run.started_at)}
                            </td>
                            <td className="py-2 pr-4">
                                {triggers[run.trigger]}
                            </td>
                            <td className="py-2 pr-4 text-right tabular-nums">
                                {run.created + run.updated}
                            </td>
                            <td className="py-2 pr-4 text-right tabular-nums">
                                {run.skipped}
                            </td>
                            <td className="py-2 pr-4 text-right tabular-nums">
                                {run.failed}
                            </td>
                            <td className="py-2 text-right whitespace-nowrap tabular-nums">
                                {duration(run.duration_seconds)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
