<?php

namespace App\Filament\Resources\BloodGroups;

use App\Filament\Resources\BloodGroups\Pages;
use App\Filament\Resources\BloodGroups\Schemas\BloodGroupForm;
use App\Filament\Resources\BloodGroups\Tables\BloodGroupsTable;
use App\Models\BloodGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;
class BloodGroupResource extends Resource
{
    protected static ?string $model = BloodGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;
    protected static UnitEnum|string|null $navigationGroup = 'General Lookups';
    protected static ?int $navigationSort = 4;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Blood Groups';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Blood Groups';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Blood Group';
    public static function form(Schema $schema): Schema
    {
        return BloodGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BloodGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBloodGroups::route('/'),
            'create' => Pages\CreateBloodGroup::route('/create'),
            'edit' => Pages\EditBloodGroup::route('/{record}/edit'),
        ];
    }
}
