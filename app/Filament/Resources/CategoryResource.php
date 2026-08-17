<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Категории';

    protected static ?string $modelLabel = 'категория';

    protected static ?string $pluralModelLabel = 'категории';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Име')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label('Слъг')
                ->required()
                ->maxLength(255),
            Select::make('parent_id')
                ->label('Родителска категория')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            TextInput::make('icon')
                ->label('Икона')
                ->maxLength(255),
            TextInput::make('sort_order')
                ->label('Ред')
                ->numeric()
                ->default(0)
                ->required(),
            Toggle::make('is_active')
                ->label('Активна')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Име')->searchable()->sortable(),
                TextColumn::make('slug')->label('Слъг')->searchable(),
                TextColumn::make('parent.name')->label('Родител')->placeholder('—'),
                TextColumn::make('sort_order')->label('Ред')->sortable(),
                IconColumn::make('is_active')->label('Активна')->boolean(),
                TextColumn::make('listings_count')->label('Обяви')->counts('listings'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
