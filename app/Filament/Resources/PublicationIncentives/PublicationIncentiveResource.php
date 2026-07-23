<?php

namespace App\Filament\Resources\PublicationIncentives;

use App\Filament\Resources\PublicationIncentives\Pages;
use App\Filament\Resources\PublicationIncentives\Schemas\PublicationIncentiveForm;
use App\Filament\Resources\PublicationIncentives\Tables\PublicationIncentivesTable;
use App\Models\PublicationIncentive;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PublicationIncentiveResource extends Resource
{
    protected static ?string $model = PublicationIncentive::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Publications';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Incentives';

    protected static ?string $pluralLabel = 'Publication Incentives';

    protected static ?string $modelLabel = 'Publication Incentive';

    public static function form(Schema $schema): Schema
    {
        return PublicationIncentiveForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicationIncentivesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPublicationIncentives::route('/'),
            'create' => Pages\CreatePublicationIncentive::route('/create'),
            'edit' => Pages\EditPublicationIncentive::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['publication.teachers']);

        if (auth()->check()) {
            /** @var \App\Models\User $user */
            $user = auth()->user();

            if ($user->hasRole(['super_admin', 'admin', 'research_team'])) {
                return $query;
            }

            if ($user->hasRole('teacher') && ! ($user->hasRole('dean') || $user->hasRole('head'))) {
                if ($user->teacher) {
                    $teacherId = $user->teacher->id;
                    $query->whereHas('publication.teachers', function ($q) use ($teacherId) {
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
                    $query->whereHas('publication', function ($pq) use ($deptId) {
                        $pq->where(function ($q) use ($deptId) {
                            $q->where('department_id', $deptId)
                              ->orWhereHas('teachers', function ($tq) use ($deptId) {
                                  $tq->where('teachers.department_id', $deptId)
                                     ->orWhereHas('departments', function ($dq) use ($deptId) {
                                         $dq->where('departments.id', $deptId);
                                      });
                              });
                        });
                    });
                }
                // Faculty-scoped user (e.g. Dean)
                elseif ($adminRole->pivot->faculty_id) {
                    $facId = $adminRole->pivot->faculty_id;
                    $query->whereHas('publication', function ($pq) use ($facId) {
                        $pq->where(function ($q) use ($facId) {
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
                    });
                }
            }
        }

        return $query;
    }
}
