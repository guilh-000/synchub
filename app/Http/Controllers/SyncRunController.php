<?php

namespace App\Http\Controllers;

use App\Enums\SyncRunStatus;
use App\Http\Resources\SyncRunResource;
use App\Models\SyncRun;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SyncRunController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SyncRunStatus::class)],
        ]);

        $runs = SyncRun::query()
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('runs/index', [
            'runs' => SyncRunResource::collection($runs),
            'filters' => ['status' => $filters['status'] ?? null],
        ]);
    }

    public function show(SyncRun $run): Response
    {
        $run->load(['failures' => fn ($query) => $query->orderBy('id')->limit(500), 'retries']);

        return Inertia::render('runs/show', [
            'run' => SyncRunResource::make($run)->resolve(),
        ]);
    }
}
