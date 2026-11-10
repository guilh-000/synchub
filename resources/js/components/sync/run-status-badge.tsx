import { CircleAlert, CircleCheck, CircleX, LoaderCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { SyncRunStatus } from '@/types';

const styles: Record<
    SyncRunStatus,
    { label: string; icon: typeof CircleCheck; className: string }
> = {
    running: {
        label: 'Running',
        icon: LoaderCircle,
        className:
            'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 [&>svg]:animate-spin',
    },
    succeeded: {
        label: 'Succeeded',
        icon: CircleCheck,
        className:
            'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    },
    partial: {
        label: 'Partial',
        icon: CircleAlert,
        className:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    },
    failed: {
        label: 'Failed',
        icon: CircleX,
        className:
            'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
    },
};

export function RunStatusBadge({ status }: { status: SyncRunStatus }) {
    const { label, icon: Icon, className } = styles[status];

    return (
        <Badge variant="outline" className={cn(className)}>
            <Icon />
            {label}
        </Badge>
    );
}
