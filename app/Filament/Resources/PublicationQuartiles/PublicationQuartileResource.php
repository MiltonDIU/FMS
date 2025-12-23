<?php

namespace App\Filament\Resources\PublicationQuartiles;

use App\Filament\Resources\PublicationQuartiles\Pages\CreatePublicationQuartile;
use App\Filament\Resources\PublicationQuartiles\Pages\EditPublicationQuartile;
use App\Filament\Resources\PublicationQuartiles\Pages\ListPublicationQuartiles;
use App\Filament\Resources\PublicationQuartiles\Schemas\PublicationQuartileForm;
use App\Filament\Resources\PublicationQuartiles\Tables\PublicationQuartilesTable;
use App\Models\PublicationQuartile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PublicationQuartileResource extends Resource
{
    protected static ?string $model = PublicationQuartile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PublicationQuartileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicationQuartilesTable::configure($table);
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
            'index' => ListPublicationQuartiles::route('/'),
            'create' => CreatePublicationQuartile::route('/create'),
            'edit' => EditPublicationQuartile::route('/{record}/edit'),
        ];
    }
}
