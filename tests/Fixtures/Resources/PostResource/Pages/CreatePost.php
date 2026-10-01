<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource\Pages;

use Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['body'] ??= 'Written by the create page.';

        return $data;
    }
}
