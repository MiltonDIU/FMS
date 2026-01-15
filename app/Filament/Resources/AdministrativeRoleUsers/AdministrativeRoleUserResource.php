<?php

namespace App\Filament\Resources\AdministrativeRoleUsers;

use App\Filament\Resources\AdministrativeRoleUsers\Pages\CreateAdministrativeRoleUser;
use App\Filament\Resources\AdministrativeRoleUsers\Pages\EditAdministrativeRoleUser;
use App\Filament\Resources\AdministrativeRoleUsers\Pages\ListAdministrativeRoleUsers;
use App\Filament\Resources\AdministrativeRoleUsers\Schemas\AdministrativeRoleUserForm;
use App\Filament\Resources\AdministrativeRoleUsers\Tables\AdministrativeRoleUsersTable;
use App\Models\UserAdministrativeRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AdministrativeRoleUserResource extends Resource
{
    protected static ?string $model = UserAdministrativeRole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static UnitEnum|string|null $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Administrative Role Users';

    protected static ?string $pluralLabel = 'Administrative Role Users';

    protected static ?string $modelLabel = 'Administrative Role User';

    public static function form(Schema $schema): Schema
    {
        return AdministrativeRoleUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdministrativeRoleUsersTable::configure($table);
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
            'index' => ListAdministrativeRoleUsers::route('/'),
            'create' => CreateAdministrativeRoleUser::route('/create'),
            'edit' => EditAdministrativeRoleUser::route('/{record}/edit'),
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
