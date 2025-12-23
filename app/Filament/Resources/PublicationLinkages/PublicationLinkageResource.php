<?php

namespace App\Filament\Resources\PublicationLinkages;

use App\Filament\Resources\PublicationLinkages\Pages\CreatePublicationLinkage;
use App\Filament\Resources\PublicationLinkages\Pages\EditPublicationLinkage;
use App\Filament\Resources\PublicationLinkages\Pages\ListPublicationLinkages;
use App\Filament\Resources\PublicationLinkages\Schemas\PublicationLinkageForm;
use App\Filament\Resources\PublicationLinkages\Tables\PublicationLinkagesTable;
use App\Models\PublicationLinkage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PublicationLinkageResource extends Resource
{
    protected static ?string $model = PublicationLinkage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PublicationLinkageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicationLinkagesTable::configure($table);
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
            'index' => ListPublicationLinkages::route('/'),
            'create' => CreatePublicationLinkage::route('/create'),
            'edit' => EditPublicationLinkage::route('/{record}/edit'),
        ];
    }
}
