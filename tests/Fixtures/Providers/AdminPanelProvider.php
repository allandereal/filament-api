<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Providers;

use Allandereal\FilamentApi\FilamentApiPlugin;
use Allandereal\FilamentApi\Tests\Fixtures\Models\Secret;
use Allandereal\FilamentApi\Tests\Fixtures\Resources\CategoryResource;
use Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([
                CategoryResource::class,
                PostResource::class,
            ])
            ->plugin(
                FilamentApiPlugin::make()
                    ->middleware(['api', 'auth:sanctum'])
                    ->perPage(2)
                    ->maxPerPage(5)
                    ->logRequests()
                    ->login()
                    ->models([
                        'secrets' => ['model' => Secret::class, 'filters' => ['name'], 'sorts' => ['name']],
                    ]),
            );
    }
}
