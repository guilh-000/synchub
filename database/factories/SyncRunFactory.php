<?php

namespace Database\Factories;

use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-2 days');

        return [
            'sync' => 'products',
            'trigger' => SyncTrigger::Schedule,
            'full' => false,
            'status' => SyncRunStatus::Succeeded,
            'read' => $read = fake()->numberBetween(0, 50),
            'created' => 0,
            'updated' => $read,
            'skipped' => 0,
            'failed' => 0,
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->modify('+'.fake()->numberBetween(1, 40).' seconds'),
        ];
    }

    public function running(): static
    {
        return $this->state(['status' => SyncRunStatus::Running, 'finished_at' => null]);
    }

    public function failed(string $error = 'WooCommerce is unreachable.'): static
    {
        return $this->state(['status' => SyncRunStatus::Failed, 'error' => $error]);
    }
}
