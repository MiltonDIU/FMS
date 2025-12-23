<?php

namespace App\Filament\Resources\AdministrativeRoles;

use App\Filament\Resources\AdministrativeRoles\Pages\CreateAdministrativeRole;
use App\Filament\Resources\AdministrativeRoles\Pages\EditAdministrativeRole;
use App\Filament\Resources\AdministrativeRoles\Pages\ListAdministrativeRoles;
use App\Filament\Resources\AdministrativeRoles\Schemas\AdministrativeRoleForm;
use App\Filament\Resources\AdministrativeRoles\Tables\AdministrativeRolesTable;
use App\Models\AdministrativeRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
class AdministrativeRoleResource extends Resource
{
    protected static ?string $model = AdministrativeRole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;
    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 4;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Administrative Roles';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Administrative Roles';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Administrative Role';

    public static function form(Schema $schema): Schema
    {
        return AdministrativeRoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdministrativeRolesTable::configure($table);
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
            'index' => ListAdministrativeRoles::route('/'),
            'create' => CreateAdministrativeRole::route('/create'),
            'edit' => EditAdministrativeRole::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
