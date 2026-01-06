<?php

namespace App\Filament\Resources\DegreeTypes;

use App\Models\DegreeType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DegreeTypeResource extends Resource
{
    protected static ?string $model = DegreeType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static \UnitEnum|string|null $navigationGroup = 'Academic Lookups';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\DegreeTypes\Schemas\DegreeTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\DegreeTypes\Tables\DegreeTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\DegreeTypes\Pages\ListDegreeTypes::route('/'),
            'create' => \App\Filament\Resources\DegreeTypes\Pages\CreateDegreeType::route('/create'),
            'edit' => \App\Filament\Resources\DegreeTypes\Pages\EditDegreeType::route('/{record}/edit'),
        ];
    }
}
