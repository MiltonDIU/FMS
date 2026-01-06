<?php

namespace App\Filament\Resources\PublicationTypes;

use App\Filament\Resources\PublicationTypes\Pages;
use App\Filament\Resources\PublicationTypes\Schemas\PublicationTypeForm;
use App\Filament\Resources\PublicationTypes\Tables\PublicationTypesTable;
use App\Models\PublicationType;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;
class PublicationTypeResource extends Resource
{
    protected static ?string $model = PublicationType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmarkSlash;


    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Publications';
    protected static ?int $navigationSort = 5;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Publication Types';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Publication Types';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Publication Type';





    public static function form(Schema $schema): Schema
    {
        return PublicationTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicationTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPublicationTypes::route('/'),
            'create' => Pages\CreatePublicationType::route('/create'),
            'edit' => Pages\EditPublicationType::route('/{record}/edit'),
        ];
    }
}
