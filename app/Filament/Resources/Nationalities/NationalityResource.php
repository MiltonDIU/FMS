<?php

namespace App\Filament\Resources\Nationalities;

use App\Filament\Resources\Nationalities\Pages;
use App\Filament\Resources\Nationalities\Schemas\NationalityForm;
use App\Filament\Resources\Nationalities\Tables\NationalitiesTable;
use App\Models\Nationality;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class NationalityResource extends Resource
{
    protected static ?string $model = Nationality::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

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