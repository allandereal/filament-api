<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource\Pages;

use Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;
}
