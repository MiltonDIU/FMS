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
use UnitEnum;
class PublicationLinkageResource extends Resource
{
    protected static ?string $model = PublicationLinkage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Publications';
    protected static ?int $navigationSort = 2;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Publication Linkages ';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Publication Linkages';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Publication Linkage';
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
