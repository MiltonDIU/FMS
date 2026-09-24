<?php

namespace App\Filament\Resources\Departments;

use App\Filament\Resources\Departments\Pages\CreateDepartment;
use App\Filament\Resources\Departments\Pages\EditDepartment;
use App\Filament\Resources\Departments\Pages\ListDepartments;
use App\Filament\Resources\Departments\RelationManagers\TeacherDepartmentRelationManager;
use App\Filament\Resources\Departments\RelationManagers\TeachersRelationManager;
use App\Filament\Resources\Departments\Schemas\DepartmentForm;
use App\Filament\Resources\Departments\Tables\DepartmentsTable;
use App\Models\Department;
use App\Models\Teacher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static UnitEnum|string|null $navigationGroup = 'Academic Structure';
    protected static ?int $navigationSort = 2;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Departments';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Departments';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Department';

    public static function form(Schema $schema): Schema
    {
        return DepartmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TeachersRelationManager::class,
            TeacherDepartmentRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit' => EditDepartment::route('/{record}/edit'),
        ];
    }

    /**
     * Apply role-based scoping to the query
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user || $user->hasRole('super_admin')) {
            return $query;
        }

        // Check user's administrative role bindings
        $adminRole = $user->administrativeRoles()
            ->wherePivot('is_active', true)
            ->whereNull('administrative_role_user.end_date')
            ->first();

        if ($adminRole && $adminRole->pivot) {
            // Department-scoped user (e.g. Head)
            if ($adminRole->pivot->department_id) {
                $query->where('departments.id', $adminRole->pivot->department_id);
            }
            // Faculty-scoped user (e.g. Dean)
            elseif ($adminRole->pivot->faculty_id) {
                $query->where('departments.faculty_id', $adminRole->pivot->faculty_id);
            }
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
