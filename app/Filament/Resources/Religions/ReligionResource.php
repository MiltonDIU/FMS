<?php

namespace App\Filament\Resources\Religions;

use App\Filament\Resources\Religions\Pages;
use App\Filament\Resources\Religions\Schemas\ReligionForm;
use App\Filament\Resources\Religions\Tables\ReligionsTable;
use App\Models\Religion;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ReligionResource extends Resource
{
    protected static ?string $model = Religion::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function form(Schema $schema): Schema
    {
        return ReligionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReligionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReligions::route('/'),
            'create' => Pages\CreateReligion::route('/create'),
            'edit' => Pages\EditReligion::route('/{record}/edit'),
        ];
    }
}