<?php

namespace Allandereal\FilamentApi\Tests\Fixtures\Resources;

use Allandereal\FilamentApi\Tests\Fixtures\Models\Category;
use Allandereal\FilamentApi\Tests\Fixtures\Resources\CategoryResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * A simple (modal) resource: it has no create or edit page.
 */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}
