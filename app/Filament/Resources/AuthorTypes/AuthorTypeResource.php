<?php

namespace App\Filament\Resources\AuthorTypes;

use App\Filament\Resources\AuthorTypes\Pages;
use App\Filament\Resources\AuthorTypes\Schemas\AuthorTypeForm;
use App\Filament\Resources\AuthorTypes\Tables\AuthorTypesTable;
use App\Models\AuthorType;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class AuthorTypeResource extends Resource
{
    protected static ?string $model = AuthorType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static UnitEnum|string|null $navigationGroup = 'General Lookups';
    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Author Types';
    protected static ?string $pluralLabel = 'Author Types';
    protected static ?string $modelLabel = 'Author Type';

    public static function form(Schema $schema): Schema
    {
        return AuthorTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuthorTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuthorTypes::route('/'),
            'create' => Pages\CreateAuthorType::route('/create'),
            'edit' => Pages\EditAuthorType::route('/{record}/edit'),
        ];
    }
}
