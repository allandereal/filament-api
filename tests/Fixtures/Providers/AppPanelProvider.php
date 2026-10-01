<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Providers;

use Allandereal\FilamentApi\FilamentApiPlugin;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Team;
use Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * A panel with tenancy: its API is at `api/app/{tenant}/...`.
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->tenant(Team::class)
            ->resources([
                PostResource::class,
            ])
            ->plugin(
                FilamentApiPlugin::make()
                    ->logRequests()
                    ->operatorFilters()
                    ->aggregates(),
            );
    }
}
