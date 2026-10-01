<?php

namespace Allandereal\FilamentApi\Pages;

use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;

/**
 * Used to create records of resources that have no create page, such as simple (modal) resources.
 *
 * @internal
 */
class CreateRecord extends BaseCreateRecord
{
    protected static string $resource;

    public static function forResource(string $resource): static
    {
        static::$resource = $resource;

        return app(static::class);
    }
}
