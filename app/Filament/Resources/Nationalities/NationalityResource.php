<?php

namespace App\Filament\Resources\Nationalities;

use App\Filament\Resources\Nationalities\Pages;
use App\Filament\Resources\Nationalities\Schemas\NationalityForm;
use App\Filament\Resources\Nationalities\Tables\NationalitiesTable;
use App\Models\Nationality;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;
class NationalityResource extends Resource
{
    protected static ?string $model = Nationality::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;
    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 8;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Nationalities';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Nationalities';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Nationality';

    public static function form(Schema $schema): Schema
    {
        return NationalityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NationalitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNationalities::route('/'),
            'create' => Pages\CreateNationality::route('/create'),
            'edit' => Pages\EditNationality::route('/{record}/edit'),
        ];
    }
}
