export type SyncRunStatus = 'running' | 'succeeded' | 'partial' | 'failed';

export type SyncRunFailure = {
    id: number;
    /** ERP code (products, stock) or store order ID (orders). */
    item_key: string;
    message: string;
};

export type SyncRun = {
    id: number;
    sync: string;
    trigger: 'schedule' | 'manual' | 'console';
    full: boolean;
    retry_of_id: number | null;
    retries?: { id: number; status: SyncRunStatus }[];
    status: SyncRunStatus;
    read: number;
    created: number;
    updated: number;
    skipped: number;
    failed: number;
    error: string | null;
    started_at: string;
    finished_at: string | null;
    duration_seconds: number | null;
    failures?: SyncRunFailure[];
};

export type SyncOverview = {
    key: string;
    title: string;
    direction: string;
    schedule: string;
    watermark: string | null;
    mapped: number;
    running: boolean;
    last_run: SyncRun | null;
};

export type HealthCheck = {
    ok: boolean;
    detail: string;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};
