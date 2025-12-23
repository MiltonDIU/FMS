<?php

namespace App\Filament\Resources\Religions;

use App\Filament\Resources\Religions\Pages;
use App\Filament\Resources\Religions\Schemas\ReligionForm;
use App\Filament\Resources\Religions\Tables\ReligionsTable;
use App\Models\Religion;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;
class ReligionResource extends Resource
{
    protected static ?string $model = Religion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;
    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 7;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Religions';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Religions';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Religion';

    public static function form(Schema $schema): Schema
    {
        return ReligionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReligionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReligions::route('/'),
            'create' => Pages\CreateReligion::route('/create'),
            'edit' => Pages\EditReligion::route('/{record}/edit'),
        ];
    }
}
