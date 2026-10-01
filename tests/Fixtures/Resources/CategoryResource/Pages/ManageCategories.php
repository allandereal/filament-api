<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Resources\CategoryResource\Pages;

use Allandereal\FilamentApi\Tests\Fixtures\Resources\CategoryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;
}
