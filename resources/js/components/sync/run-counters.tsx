import { cn } from '@/lib/utils';
import type { SyncRun } from '@/types';

const counters: {
    key: keyof Pick<
        SyncRun,
        'read' | 'created' | 'updated' | 'skipped' | 'failed'
    >;
    label: string;
}[] = [
    { key: 'read', label: 'Read' },
    { key: 'created', label: 'Created' },
    { key: 'updated', label: 'Updated' },
    { key: 'skipped', label: 'Unchanged' },
    { key: 'failed', label: 'Failed' },
];

export function RunCounters({
    run,
    className,
}: {
    run: SyncRun;
    className?: string;
}) {
    return (
        <dl className={cn('grid grid-cols-5 gap-2', className)}>
            {counters.map(({ key, label }) => (
                <div key={key} className="rounded-lg bg-muted/50 px-3 py-2">
                    <dt className="text-xs text-muted-foreground">{label}</dt>
                    <dd
                        className={cn(
                            'text-lg font-semibold tabular-nums',
                            key === 'failed' &&
                                run.failed > 0 &&
                                'text-red-600 dark:text-red-400',
                        )}
                    >
                        {run[key]}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
