<?php

namespace App\Filament\Resources\Publications;

use App\Filament\Resources\Publications\Pages\CreatePublication;
use App\Filament\Resources\Publications\Pages\EditPublication;
use App\Filament\Resources\Publications\Pages\ListPublications;
use App\Filament\Resources\Publications\Schemas\PublicationForm;
use App\Filament\Resources\Publications\Tables\PublicationsTable;
use App\Models\Publication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PublicationResource extends Resource
{
    protected static ?string $model = Publication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static UnitEnum|string|null $navigationGroup = 'Publications';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Publications';
    protected static ?string $pluralLabel = 'Publications';
    protected static ?string $modelLabel = 'Publication';

    public static function form(Schema $schema): Schema
    {
        return PublicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicationsTable::configure($table);
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
            'index' => ListPublications::route('/'),
            'create' => CreatePublication::route('/create'),
            'edit' => EditPublication::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin', 'dean', 'head', 'registrar', 'research_team'])) {
            return true;
        }

        return $user->can('ViewAny:Publication');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check()) {
            /** @var \App\Models\User $user */
            $user = auth()->user();

            if ($user->hasRole(['super_admin', 'admin', 'research_team'])) {
                return $query;
            }

            if ($user->hasRole('teacher') && ! ($user->hasRole('dean') || $user->hasRole('head'))) {
                if ($user->teacher) {
                    $teacherId = $user->teacher->id;
                    $query->whereHas('teachers', function ($q) use ($teacherId) {
                        $q->where('teachers.id', $teacherId);
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }
                return $query;
            }

            // Check user's administrative role bindings (Dean, Head, etc.)
            $adminRole = $user->administrativeRoles()
                ->wherePivot("is_active", true)
                ->whereNull("administrative_role_user.end_date")
                ->first();

            if ($adminRole && $adminRole->pivot) {
                // Department-scoped user (e.g. Head)
                if ($adminRole->pivot->department_id) {
                    $deptId = $adminRole->pivot->department_id;
                    $query->where(function ($q) use ($deptId) {
                        $q->where('department_id', $deptId)
                          ->orWhereHas('teachers', function ($tq) use ($deptId) {
                              dtq->where('teachers.department_id', $deptId)
                                 ->orWhereHas('departments', function ($dq) use ($deptId) {
                                     $dq->where('departments.id', $deptId);
                                  });
                          });
                    });
                }
                // Faculty-scoped user (e.g. Dean)
                elseif ($adminRole->pivot->faculty_id) {
                    $facId = $adminRole->pivot->faculty_id;
                    $query->where(function ($q) use ($facId) {
                        $q->where('faculty_id', $facId)
                          ->orWhereHas('department', function ($dq) use ($facId) {
                              $dq->where('faculty_id', $facId);
                          })
                          ->orWhereHas('teachers', function ($tq) use ($facId) {
                              $tq->whereHas('department', function ($dq) use ($facId) {
                                   $dq->where('faculty_id', $facId);
                              })->orWhereHas('departments', function ($dq) use ($facId) {
                                   $dq->where('faculty_id', $facId);
                              });
                          });
                    });
                }
            }
        }

        return $query;
    }
}
