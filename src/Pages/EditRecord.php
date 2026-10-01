<?php

namespace Allandereal\FilamentApi\Pages;

use Filament\Resources\Pages\EditRecord as BaseEditRecord;

/**
 * Used to update records of resources that have no edit page, such as simple (modal) resources.
 *
 * @internal
 */
class EditRecord extends BaseEditRecord
{
    protected static string $resource;

    public static function forResource(string $resource): static
    {
        static::$resource = $resource;

        return app(static::class);
    }
}
