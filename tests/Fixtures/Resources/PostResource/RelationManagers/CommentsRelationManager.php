<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('body')->searchable(),
        ]);
    }
}
