<?php

namespace App\Filament\Resources\NamePrefixes;

use App\Filament\Resources\NamePrefixes\Pages;
use App\Filament\Resources\NamePrefixes\RelationManagers;
use App\Filament\Resources\NamePrefixes\Schemas\NamePrefixForm;
use App\Filament\Resources\NamePrefixes\Tables\NamePrefixesTable;
use App\Models\NamePrefix;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The titles written before a teacher's name — "Professor Dr.", "Engr.", "Ms.".
 *
 * A list rather than a text box, because the old database wrote twelve titles
 * twenty-five ways: "Professor Dr.", "Prof. Dr.", "Professor Dr" and "Prof.Dr."
 * are one title, and a directory that prints all four looks careless. Keeping
 * the list here means the spelling is decided once, and a title nobody
 * anticipated can be added without a deployment.
 *
 * One row holds a whole stack. "Professor Dr. Engr." is a single choice, not
 * three tickboxes, so the order it is written in is settled by the row rather
 * than by whatever draws the name.
 */
class NamePrefixResource extends Resource
{
    protected static ?string $model = NamePrefix::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static UnitEnum|string|null $navigationGroup = 'Academic Structure';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Name Prefixes';

    protected static ?string $pluralLabel = 'Name Prefixes';

    protected static ?string $modelLabel = 'Name Prefix';

    public static function form(Schema $schema): Schema
    {
        return NamePrefixForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NamePrefixesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TeachersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNamePrefixes::route('/'),
            'create' => Pages\CreateNamePrefix::route('/create'),
            'edit' => Pages\EditNamePrefix::route('/{record}/edit'),
        ];
    }
}
