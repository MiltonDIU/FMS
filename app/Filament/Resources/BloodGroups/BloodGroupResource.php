<?php

namespace App\Filament\Resources\BloodGroups;

use App\Filament\Resources\BloodGroups\Pages;
use App\Filament\Resources\BloodGroups\Schemas\BloodGroupForm;
use App\Filament\Resources\BloodGroups\Tables\BloodGroupsTable;
use App\Models\BloodGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BloodGroupResource extends Resource
{
    protected static ?string $model = BloodGroup::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

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