<?php

namespace App\Sync;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The syncs SyncHub runs, by key (config/synchub.php `syncs`).
 */
class SyncRegistry
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->classes());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->classes());
    }

    public function get(string $key): Sync
    {
        $class = $this->classes()[$key] ?? throw new InvalidArgumentException("Unknown sync [{$key}].");

        /** @var Sync */
        return $this->container->make($class);
    }

    /**
     * @return list<Sync>
     */
    public function all(): array
    {
        return array_map($this->get(...), $this->keys());
    }

    /**
     * @return array<string, class-string<Sync>>
     */
    private function classes(): array
    {
        /** @var array<string, class-string<Sync>> */
        return config('synchub.syncs');
    }
}
