<?php

namespace App\Filament\Resources\Teachers;

use App\Filament\Resources\Teachers\Pages\CreateTeacher;
use App\Filament\Resources\Teachers\Pages\EditTeacher;
use App\Filament\Resources\Teachers\Pages\ListTeachers;
use App\Filament\Resources\Teachers\RelationManagers;
use App\Filament\Resources\Teachers\Schemas\TeacherForm;
use App\Filament\Resources\Teachers\Tables\TeachersTable;
use App\Models\Teacher;
use App\Models\Department;
use App\Models\Faculty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;
    protected static \UnitEnum|string|null $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin', 'dean', 'head', 'registrar'])) {
            return true;
        }

        return $user->can('ViewAny:Teacher');
    }

    public static function form(Schema $schema): Schema
    {
        return TeacherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeachersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTeachers::route('/'),
            'create' => CreateTeacher::route('/create'),
            'view'   => Pages\ViewTeacher::route('/{record}'),
            'edit'   => EditTeacher::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check()) {
            /** @var \App\Models\User $user */
            $user = auth()->user();

            if ($user->hasRole(['super_admin', 'admin'])) {
                return $query;
            }

            // 1. Check user's administrative role bindings (Dean, Head, etc.) FIRST
            $adminRole = $user->administrativeRoles()
                ->wherePivot('is_active', true)
                ->whereNull('administrative_role_user.end_date')
                ->first();

            if ($adminRole && $adminRole->pivot) {
                // Department-scoped user (e.g. Head)
                if ($adminRole->pivot->department_id) {
                    $deptId = $adminRole->pivot->department_id;
                    return $query->where(function ($q) use ($deptId) {
                        $q->where('department_id', $deptId)
                          ->orWhereHas('departments', function ($dq) use ($deptId) {
                              $dq->where('departments.id', $deptId);
                          });
                    });
                }
                // Faculty-scoped user (e.g. Dean)
                elseif ($adminRole->pivot->faculty_id) {
                  $facId = $adminRole->pivot->faculty_id;
                    $deptIds = Department::where('faculty_id', $facId)->pluck('id')->toArray();
                    return $query->where(function ($q) use ($facId, $deptIds) {
                        $q->whereIn('department_id', $deptIds)
                          ->orWhereHas('department', function ($dq) use ($facId) {
                              $dq->where('faculty_id', $facId);
                          })
                          ->orWhereHas('departments', function ($dq) use ($facId, $deptIds) {
                              $dq->whereIn('departments.id', $deptIds)
                                 ->orWhere('faculty_id', $facId);
                          });
                    });
                }
            }

            // 2. Spatie role check for Dean / Head fallback
            if ($user->hasRole('dean') || $user->hasRole('head')) {
                return $query;
            }

            // 3. Regular teacher check (only own profile)
            if ($user->hasRole('teacher') || $user->isTeacher()) {
                return $query->where('user_id', $user->id);
            }
        }

        return $query;
    }
}
