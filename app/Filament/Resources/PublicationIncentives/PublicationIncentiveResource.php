<?php

namespace App\Filament\Resources\PublicationIncentives;

use App\Filament\Resources\PublicationIncentives\Pages;
use App\Filament\Resources\PublicationIncentives\Schemas\PublicationIncentiveForm;
use App\Filament\Resources\PublicationIncentives\Tables\PublicationIncentivesTable;
use App\Models\PublicationIncentive;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PublicationIncentiveResource extends Resource
{
    protected static ?string $model = PublicationIncentive::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Publications';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Incentives';

    protected static ?string $pluralLabel = 'Publication Incentives';

    protected static ?string $modelLabel = 'Publication Incentive';

    public static function form(Schema $schema): Schema
    {
        return PublicationIncentiveForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicationIncentivesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPublicationIncentives::route('/'),
            'create' => Pages\CreatePublicationIncentive::route('/create'),
            'edit' => Pages\EditPublicationIncentive::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['publication.teachers']);
    }
}
