<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Providers;

use Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * A panel without the plugin: none of its resources are exposed.
 */
class OtherPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('other')
            ->path('other')
            ->resources([
                PostResource::class,
            ]);
    }
}
