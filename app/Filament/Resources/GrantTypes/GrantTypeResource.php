<?php

namespace App\Filament\Resources\GrantTypes;

use App\Filament\Resources\GrantTypes\Pages\CreateGrantType;
use App\Filament\Resources\GrantTypes\Pages\EditGrantType;
use App\Filament\Resources\GrantTypes\Pages\ListGrantTypes;
use App\Filament\Resources\GrantTypes\Schemas\GrantTypeForm;
use App\Filament\Resources\GrantTypes\Tables\GrantTypesTable;
use App\Models\GrantType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class GrantTypeResource extends Resource
{
    protected static ?string $model = GrantType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBoltSlash;
    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Publications';
    protected static ?int $navigationSort = 1;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Grant Types';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Grant Types';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Grant Type';
    public static function form(Schema $schema): Schema
    {
        return GrantTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GrantTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrantTypes::route('/'),
            'create' => CreateGrantType::route('/create'),
            'edit' => EditGrantType::route('/{record}/edit'),
        ];
    }
}
