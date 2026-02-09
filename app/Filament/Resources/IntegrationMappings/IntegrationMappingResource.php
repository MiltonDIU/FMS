<?php

namespace App\Filament\Resources\IntegrationMappings;

use App\Filament\Resources\IntegrationMappings\Pages\CreateIntegrationMapping;
use App\Filament\Resources\IntegrationMappings\Pages\EditIntegrationMapping;
use App\Filament\Resources\IntegrationMappings\Pages\ListIntegrationMappings;
use App\Filament\Resources\IntegrationMappings\Schemas\IntegrationMappingForm;
use App\Filament\Resources\IntegrationMappings\Tables\IntegrationMappingsTable;
use App\Models\IntegrationMapping;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class IntegrationMappingResource extends Resource
{
    protected static ?string $model = IntegrationMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Integration Mappings';

    protected static ?string $pluralLabel = 'Integration Mappings';

    protected static ?string $modelLabel = 'Integration Mapping';

    public static function form(Schema $schema): Schema
    {
        return IntegrationMappingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntegrationMappingsTable::configure($table);
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
            'index' => ListIntegrationMappings::route('/'),
            'create' => CreateIntegrationMapping::route('/create'),
            'edit' => EditIntegrationMapping::route('/{record}/edit'),
        ];
    }
}
