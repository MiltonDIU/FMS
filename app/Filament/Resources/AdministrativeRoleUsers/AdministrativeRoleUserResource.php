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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // Check user's administrative role bindings
        $adminRole = $user->administrativeRoles()
            ->wherePivot('is_active', true)
            ->whereNull('administrative_role_user.end_date')
            ->first();

        if ($adminRole && $adminRole->pivot) {
            // Department-scoped user
            if ($adminRole->pivot->department_id) {
                // Can see:
                // 1. Roles assigned to their specific department
                // 2. Roles assigned to NO department/faculty (Global roles? Maybe not. Let's stick to their dept)
                $query->where('department_id', $adminRole->pivot->department_id);
            }
            // Faculty-scoped user
            elseif ($adminRole->pivot->faculty_id) {
                // Can see:
                // 1. Roles assigned to their faculty
                // 2. Roles assigned to departments WITHIN their faculty
                $query->where(function ($q) use ($adminRole) {
                    $q->where('faculty_id', $adminRole->pivot->faculty_id)
                      ->orWhereHas('department', function ($dq) use ($adminRole) {
                          $dq->where('faculty_id', $adminRole->pivot->faculty_id);
                      });
                });
            }
        }

        return $query;
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
