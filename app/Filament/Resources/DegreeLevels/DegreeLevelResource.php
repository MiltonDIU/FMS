<?php

namespace App\Filament\Resources\DegreeLevels;

use App\Filament\Resources\DegreeLevels\Pages\ManageDegreeLevels;
use App\Models\DegreeLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DegreeLevelResource extends Resource
{
    protected static ?string $model = DegreeLevel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'Academic Lookups';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\DegreeLevels\Schemas\DegreeLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\DegreeLevels\Tables\DegreeLevelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\DegreeLevels\Pages\ListDegreeLevels::route('/'),
            'create' => \App\Filament\Resources\DegreeLevels\Pages\CreateDegreeLevel::route('/create'),
            'edit' => \App\Filament\Resources\DegreeLevels\Pages\EditDegreeLevel::route('/{record}/edit'),
        ];
    }
}
