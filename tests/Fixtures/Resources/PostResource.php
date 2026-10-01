<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Resources;

use Allandereal\FilamentApi\Tests\Fixtures\Models\Post;
use Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource\Pages;
use Allandereal\FilamentApi\Tests\Fixtures\Resources\PostResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(20),
            Forms\Components\Textarea::make('body'),
            Forms\Components\Select::make('status')
                ->options(['draft' => 'Draft', 'published' => 'Published', 'locked' => 'Locked'])
                ->in(['draft', 'published', 'locked'])
                ->default('draft')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('created_at')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['draft' => 'Draft', 'published' => 'Published', 'locked' => 'Locked']),
                Tables\Filters\Filter::make('has_body')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('body')),
                Tables\Filters\QueryBuilder::make()
                    ->constraints([
                        Tables\Filters\QueryBuilder\Constraints\TextConstraint::make('title'),
                    ]),
            ])
            // Deferred filters keep their form state apart from the applied state.
            ->deferFilters();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
