<?php

namespace App\Filament\Resources\ResultTypes;

use App\Models\ResultType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResultTypeResource extends Resource
{
    protected static ?string $model = ResultType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static \UnitEnum|string|null $navigationGroup = 'Academic Lookups';

    protected static ?string $recordTitleAttribute = 'type_name';

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\ResultTypes\Schemas\ResultTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\ResultTypes\Tables\ResultTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\ResultTypes\Pages\ListResultTypes::route('/'),
            'create' => \App\Filament\Resources\ResultTypes\Pages\CreateResultType::route('/create'),
            'edit' => \App\Filament\Resources\ResultTypes\Pages\EditResultType::route('/{record}/edit'),
        ];
    }
}
