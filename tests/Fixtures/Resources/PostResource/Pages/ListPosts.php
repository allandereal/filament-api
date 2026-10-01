<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource\Pages;

use Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'drafts' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),
        ];
    }
}
