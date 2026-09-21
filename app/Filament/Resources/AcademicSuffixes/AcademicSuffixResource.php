<?php

namespace App\Filament\Resources\AcademicSuffixes;

use App\Filament\Resources\AcademicSuffixes\Pages;
use App\Filament\Resources\AcademicSuffixes\RelationManagers;
use App\Filament\Resources\AcademicSuffixes\Schemas\AcademicSuffixForm;
use App\Filament\Resources\AcademicSuffixes\Tables\AcademicSuffixesTable;
use App\Models\AcademicSuffix;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The qualifications written after a teacher's name — PhD, MBBS, FCPS.
 *
 * Many per teacher, because "PhD, MBA" is a real pair, and a list rather than
 * free text for the same reason as the prefixes: sixteen people carried a
 * doctorate in the old database and it was spelled three ways between them.
 *
 * The list is short on purpose. Only what the data actually holds is seeded —
 * PhD and MBA — rather than every qualification a university might one day see.
 * This screen is where the rest get added, one line at a time, by whoever knows
 * they are needed.
 */
class AcademicSuffixResource extends Resource
{
    protected static ?string $model = AcademicSuffix::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static UnitEnum|string|null $navigationGroup = 'Academic Structure';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Academic Suffixes';

    protected static ?string $pluralLabel = 'Academic Suffixes';

    protected static ?string $modelLabel = 'Academic Suffix';

    public static function form(Schema $schema): Schema
    {
        return AcademicSuffixForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AcademicSuffixesTable::configure($table);
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
            'index' => Pages\ListAcademicSuffixes::route('/'),
            'create' => Pages\CreateAcademicSuffix::route('/create'),
            'edit' => Pages\EditAcademicSuffix::route('/{record}/edit'),
        ];
    }
}
