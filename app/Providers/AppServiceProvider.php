<?php

namespace App\Providers;

use App\Integrations\WooCommerce\WooCommerceClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WooCommerceClient::class, function (): WooCommerceClient {
            $credentials = $this->wooCommerceCredentials();

            return new WooCommerceClient(
                baseUrl: $credentials['base_url'],
                username: $credentials['username'],
                password: $credentials['password'],
                timeout: (int) config('synchub.woocommerce.timeout'),
            );
        });
    }

    /**
     * The store's credentials: env variables first, then the file the Docker
     * stack's `shop-init` writes.
     *
     * @return array{base_url: string, username: string, password: string}
     */
    private function wooCommerceCredentials(): array
    {
        /** @var array{base_url?: string, username?: string, application_password?: string} $file */
        $file = File::exists($path = (string) config('synchub.woocommerce.credentials_file'))
            ? (array) json_decode(File::get($path), true)
            : [];

        return [
            'base_url' => (string) (config('synchub.woocommerce.base_url') ?? $file['base_url'] ?? ''),
            'username' => (string) (config('synchub.woocommerce.username') ?? $file['username'] ?? ''),
            'password' => (string) (config('synchub.woocommerce.password') ?? $file['application_password'] ?? ''),
        ];
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
