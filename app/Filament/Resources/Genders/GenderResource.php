<?php

namespace App\Filament\Resources\Genders;

use App\Filament\Resources\Genders\Pages;
use App\Filament\Resources\Genders\Schemas\GenderForm;
use App\Filament\Resources\Genders\Tables\GendersTable;
use App\Models\Gender;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class GenderResource extends Resource
{
    protected static ?string $model = Gender::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function form(Schema $schema): Schema
    {
        return GenderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GendersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGenders::route('/'),
            'create' => Pages\CreateGender::route('/create'),
            'edit' => Pages\EditGender::route('/{record}/edit'),
        ];
    }
}