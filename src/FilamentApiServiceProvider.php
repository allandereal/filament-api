<?php

namespace Allandereal\FilamentApi;

use Allandereal\FilamentApi\Facades\FilamentApi as FilamentApiFacade;
use Allandereal\FilamentApi\Models\ApiRequest;
use Allandereal\FilamentApi\Widgets\ApiRequestsOverview;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentApiServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-api';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasViews()
            ->hasRoute('api');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentApi::class);
    }

    public function packageBooted(): void
    {
        // The widget is only used on the API logs page, so it isn't registered with the panel's dashboard widgets.
        Livewire::component('filament-api.api-requests-overview', ApiRequestsOverview::class);

        // The request logs table is created by `php artisan migrate`. Publish the migration to customize it.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'filament-api-migrations');
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (! $this->isLoggingRequests()) {
                return;
            }

            $schedule->command('model:prune', ['--model' => [ApiRequest::class]])
                ->daily()
                ->name('filament-api:prune-request-logs');
        });
    }

    protected function isLoggingRequests(): bool
    {
        foreach (FilamentApiFacade::getPanels() as $panel) {
            $plugin = FilamentApiFacade::getPlugin($panel);

            if ($plugin->isLoggingRequests() && ($plugin->getLogRetention() !== null)) {
                return true;
            }
        }

        return false;
    }
}
