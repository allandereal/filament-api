<?php

namespace Allandereal\FilamentApi\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array<\Filament\Panel> getPanels()
 * @method static \Allandereal\FilamentApi\FilamentApiPlugin getPlugin(\Filament\Panel $panel)
 * @method static array<string, class-string<\Filament\Resources\Resource>> getResourceEndpoints(\Filament\Panel $panel)
 * @method static string getResourceSlug(string $resource)
 * @method static array<string, class-string<\Filament\Resources\RelationManagers\RelationManager>> getRelationManagers(string $resource)
 *
 * @see \Allandereal\FilamentApi\FilamentApi
 */
class FilamentApi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Allandereal\FilamentApi\FilamentApi::class;
    }
}
