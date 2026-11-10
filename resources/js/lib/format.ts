const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
    ['second', 1],
];

/** "3 minutes ago", "yesterday". */
export function timeAgo(iso: string): string {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size || unit === 'second') {
            return relative.format(Math.round(seconds / size), unit);
        }
    }

    return '';
}

export function dateTime(iso: string): string {
    return new Date(iso).toLocaleString('en', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });
}

export function duration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    return seconds < 60
        ? `${seconds.toFixed(1)} s`
        : `${Math.floor(seconds / 60)} min ${Math.round(seconds % 60)} s`;
}
