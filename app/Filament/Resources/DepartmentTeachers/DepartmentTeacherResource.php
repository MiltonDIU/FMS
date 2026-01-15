<?php

namespace App\Filament\Resources\DepartmentTeachers;

use App\Filament\Resources\DepartmentTeachers\Pages\ListDepartmentTeachers;
use App\Filament\Resources\DepartmentTeachers\Schemas\DepartmentTeacherForm;
use App\Filament\Resources\DepartmentTeachers\Tables\DepartmentTeachersTable;
use App\Models\DepartmentTeacher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DepartmentTeacherResource extends Resource
{
    protected static ?string $model = DepartmentTeacher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static UnitEnum|string|null $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Department Teachers';

    protected static ?string $pluralLabel = 'Department Teachers';

    protected static ?string $modelLabel = 'Department Teacher';

    public static function form(Schema $schema): Schema
    {
        return DepartmentTeacherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentTeachersTable::configure($table);
    }

    /**
     * Apply role-based scoping to the query
     */
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
                $query->where('department_id', $adminRole->pivot->department_id);
            }
            // Faculty-scoped user
            elseif ($adminRole->pivot->faculty_id) {
                $query->whereHas('department', function($q) use ($adminRole) {
                    $q->where('faculty_id', $adminRole->pivot->faculty_id);
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
            'index' => ListDepartmentTeachers::route('/'),
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
