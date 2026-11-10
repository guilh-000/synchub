<?php

namespace App\Integrations\WooCommerce;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The part of the WooCommerce REST API (v3) SyncHub uses.
 *
 * Authenticates with a WordPress application password (HTTP Basic). Network
 * failures, 429 and 5xx are retried with a growing delay; any other error is
 * the caller's to handle.
 */
class WooCommerceClient
{
    /** Delays between attempts, in milliseconds. */
    private const array RETRY_DELAYS = [500, 2000, 5000];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = 30,
    ) {}

    /**
     * Creates and updates products in one request (at most 100 in all).
     *
     * @param  list<array<string, mixed>>  $create
     * @param  list<array<string, mixed>>  $update  each with the store's `id`
     * @return array{create: list<BatchItemResult>, update: list<BatchItemResult>}
     */
    public function batchProducts(array $create, array $update): array
    {
        $body = $this->send('post', 'products/batch', array_filter([
            'create' => $create,
            'update' => $update,
        ]));

        /** @var array{create?: list<array{id?: int, error?: array{code?: string, message?: string, data?: array<string, mixed>}}>, update?: list<array{id?: int, error?: array{code?: string, message?: string, data?: array<string, mixed>}}>} $body */
        return [
            'create' => array_map(BatchItemResult::fromResponse(...), $body['create'] ?? []),
            'update' => array_map(BatchItemResult::fromResponse(...), $body['update'] ?? []),
        ];
    }

    /**
     * How many products the store has, or an exception when it cannot be
     * reached. One quick attempt, no retries: meant for health checks.
     */
    public function productCount(): int
    {
        try {
            $response = Http::baseUrl(rtrim($this->baseUrl, '/').'/wp-json/wc/v3/')
                ->withBasicAuth($this->username, $this->password)
                ->acceptJson()
                ->timeout(5)
                ->get('products', ['per_page' => 1, 'status' => 'any', '_fields' => 'id'])
                ->throw();
        } catch (RequestException $exception) {
            throw new WooCommerceException("WooCommerce answered {$exception->response->status()}.", previous: $exception);
        } catch (ConnectionException $exception) {
            throw new WooCommerceException('WooCommerce is unreachable.', previous: $exception);
        }

        return (int) $response->header('X-WP-Total');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    private function send(string $method, string $path, array $payload = []): array
    {
        try {
            $response = $this->request()->{$method}($path, $payload)->throw();
        } catch (RequestException $exception) {
            throw new WooCommerceException(
                "WooCommerce answered {$exception->response->status()} to {$method} {$path}: ".$exception->response->body(),
                previous: $exception,
            );
        } catch (ConnectionException $exception) {
            throw new WooCommerceException("WooCommerce is unreachable: {$exception->getMessage()}", previous: $exception);
        }

        return (array) $response->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/').'/wp-json/wc/v3/')
            ->withBasicAuth($this->username, $this->password)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->retry(self::RETRY_DELAYS, when: fn (Throwable $exception): bool => self::isTransient($exception));
    }

    private static function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->status() === 429 || $exception->response->serverError());
    }
}
