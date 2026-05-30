<?php

namespace Keloola\Quota;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Keloola\Quota\Console\ResetCounterQuotasCommand;
use Keloola\Quota\Console\ListMetricsCommand;
use Keloola\Quota\Http\Middleware\VerifySsoSignature;
use Keloola\Quota\Services\QuotaManager;
use Keloola\Quota\Services\QuotaProvisioner;

class QuotaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/keloola-quota.php', 'keloola-quota');

        $this->app->singleton('keloola.quota', fn () => new QuotaManager());
        $this->app->alias('keloola.quota', QuotaManager::class);

        $this->app->singleton(QuotaProvisioner::class, fn () => new QuotaProvisioner());
    }

    public function boot(): void
    {
        // Load and Publish translations
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'keloola-quota');
        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/keloola-quota'),
        ], 'keloola-quota-lang');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/keloola-quota.php' => config_path('keloola-quota.php'),
        ], 'keloola-quota-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'keloola-quota-migrations');

        // Publish seeders
        $this->publishes([
            __DIR__ . '/../database/seeders' => database_path('seeders'),
        ], 'keloola-quota-seeders');

        // Load migrations directly (so a host can run them without publishing)
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register the SSO signature middleware alias.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('keloola.quota.sso', VerifySsoSignature::class);
        $router->aliasMiddleware('keloola.quota.context', \Keloola\Quota\Http\Middleware\SetQuotaContext::class);
        $router->aliasMiddleware('keloola.quota.check', \Keloola\Quota\Http\Middleware\CheckQuota::class);

        // Register provisioning routes (SSO push endpoints).
        if (config('keloola-quota.provisioning.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/provisioning.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ResetCounterQuotasCommand::class,
                ListMetricsCommand::class,
            ]);
        }
    }
}
