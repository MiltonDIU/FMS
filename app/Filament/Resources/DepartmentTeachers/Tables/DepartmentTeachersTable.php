<?php

namespace App\Filament\Resources\DepartmentTeachers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Faculty;
use App\Models\Department;

class DepartmentTeachersTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();
        $adminRole = null;

        if (!$user->hasRole('super_admin')) {
            $adminRole = $user->administrativeRoles()
                ->wherePivot('is_active', true)
                ->whereNull('administrative_role_user.end_date')
                ->first();
        }

        return $table
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('teacher.employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('teacher.full_name')
                    ->label('Teacher Name')
                    ->formatStateUsing(fn ($record) => $record->teacher->first_name . ' '  .$record->teacher->middle_name . ' ' . $record->teacher->last_name)
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('teacher.designation.name')
                    ->label('Designation')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('admin_roles')
                    ->label('Admin Role')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—')
                    ->state(function ($record) {
                        $user = $record->teacher?->user;
                        if (!$user) return null;

                        $loggedInUser = auth()->user();
                        if ($loggedInUser) {
                            $loggedInAdminRole = $loggedInUser->administrativeRoles()
                                ->wherePivot('is_active', true)
                                ->whereNull('administrative_role_user.end_date')
                                ->first();

                            if ($loggedInAdminRole && $loggedInAdminRole->pivot) {
                                if ($loggedInAdminRole->pivot->department_id) {
                                    $scopedDeptId = $loggedInAdminRole->pivot->department_id;
                                    $roles = $user->administrativeRoles()
                                        ->wherePivot('is_active', true)
                                        ->wherePivot('department_id', $scopedDeptId)
                                        ->whereNull('administrative_role_user.end_date')
                                        ->get();

                                    return $roles->pluck('name')->toArray();
                                } elseif ($loggedInAdminRole->pivot->faculty_id) {
                                    $scopedFacId = $loggedInAdminRole->pivot->faculty_id;
                                    $scopedDeptIds = \App\Models\Department::where('faculty_id', $scopedFacId)->pluck('id')->toArray();

                                    $roles = $user->administrativeRoles()
                                        ->wherePivot('is_active', true)
                                        ->whereNull('administrative_role_user.end_date')
                                        ->where(function ($q) use ($scopedFacId, $scopedDeptIds) {
                                            $q->where('administrative_role_user.faculty_id', $scopedFacId)
                                              ->orWhereIn('administrative_role_user.department_id', $scopedDeptIds);
                                        })
                                        ->get();

                                    return $roles->pluck('name')->toArray();
                                }
                            }
                        }

                        $allRoles = $user->administrativeRoles()
                            ->wherePivot('is_active', true)
                            ->whereNull('administrative_role_user.end_date')
                            ->get();

                        if ($allRoles->isEmpty()) {
                            return null;
                        }

                        return $allRoles->map(function ($ar) {
                            $scopeStr = '';
                            if ($ar->pivot->department_id) {
                                $deptName = \App\Models\Department::find($ar->pivot->department_id)?->name;
                                $scopeStr = $deptName ? " ({$deptName})" : '';
                            } elseif ($ar->pivot->faculty_id) {
                                $facName = \App\Models\Faculty::find($ar->pivot->faculty_id)?->name;
                                $scopeStr = $facName ? " ({$facName})" : '';
                            }
                            return $ar->name . $scopeStr;
                        })->toArray();
                    })
                    ->toggleable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('department.faculty.name')
                    ->label('Faculty')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('jobType.name')
                    ->label('Job Type')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('teacher.employmentStatus.name')
                    ->label('Teacher Status')
                    ->badge()
                    ->color(fn ($record) => $record->teacher?->employmentStatus?->color ?? 'gray')
                    ->toggleable(),

                TextColumn::make('sort_order')
                    ->label('Sort Order')
                    ->sortable(),

                TextColumn::make('assignedBy.name')
                    ->label('Assigned By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('faculty_department')
                    ->form([
                        Select::make('faculty_id')
                            ->label('Faculty')
                            ->options(function () use ($adminRole) {
                                $query = Faculty::query();
                                if ($adminRole && $adminRole->pivot) {
                                     if ($adminRole->pivot->faculty_id) {
                                         $query->where('id', $adminRole->pivot->faculty_id);
                                     } elseif ($adminRole->pivot->department_id) {
                                         $department = Department::find($adminRole->pivot->department_id);
                                         if ($department) {
                                              $query->where('id', $department->faculty_id);
                                         }
                                     }
                                }
                                return $query->pluck('name', 'id');
                            })
                            ->live()
                            ->afterStateUpdated(fn ($set) => $set('department_id', null)),

                        Select::make('department_id')
                            ->label('Department')
                            ->options(function ($get) use ($adminRole) {
                                $query = Department::query();

                                // User Scoping
                                if ($adminRole && $adminRole->pivot) {
                                    if ($adminRole->pivot->department_id) {
                                        $query->where('id', $adminRole->pivot->department_id);
                                        return $query->pluck('name', 'id');
                                    } elseif ($adminRole->pivot->faculty_id) {
                                        $query->where('faculty_id', $adminRole->pivot->faculty_id);
                                    }
                                }

                                // Dependency Logic
                                $selectedFacultyId = $get('faculty_id');
                                if ($selectedFacultyId) {
                                    $query->where('faculty_id', $selectedFacultyId);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $user = auth()->user();
                        $adminRole = null;

                        if ($user && ! $user->hasRole('super_admin')) {
                            $adminRole = $user->administrativeRoles()
                                ->wherePivot('is_active', true)
                                ->whereNull('administrative_role_user.end_date')
                                ->first();
                        }

                        // Enforce scoped-admin restrictions (the selection alone is not trusted)
                        if ($adminRole && $adminRole->pivot) {
                            if ($adminRole->pivot->department_id) {
                                $data['department_id'] = $adminRole->pivot->department_id;
                            } elseif ($adminRole->pivot->faculty_id) {
                                $data['faculty_id'] = $adminRole->pivot->faculty_id;
                            }
                        }

                        return $query
                            ->when(
                                $data['faculty_id'] ?? null,
                                fn (Builder $query, $id) => $query->whereHas('department', fn ($q) => $q->where('faculty_id', $id))
                            )
                            ->when(
                                $data['department_id'] ?? null,
                                fn (Builder $query, $id) => $query->where('department_id', $id)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (!empty($data['faculty_id'])) {
                            $faculty = Faculty::find($data['faculty_id']);
                            if ($faculty) {
                                $indicators['faculty_id'] = 'Faculty: ' . $faculty->name;
                            }
                        }

                        if (!empty($data['department_id'])) {
                            $department = Department::find($data['department_id']);
                            if ($department) {
                                $indicators['department_id'] = 'Department: ' . $department->name;
                            }
                        }

                        return $indicators;
                    }),

                SelectFilter::make('administrative_role')
                    ->label('Admin Role')
                    ->options(\App\Models\AdministrativeRole::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('teacher.user.administrativeRoles', function (Builder $q) use ($data) {
                                $q->where('administrative_roles.id', $data['value'])
                                  ->where('administrative_role_user.is_active', true)
                                  ->whereNull('administrative_role_user.end_date');
                            });
                        }
                    }),

                SelectFilter::make('teacher_designation')
                    ->label('Designation')
                    ->options(\App\Models\Designation::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('teacher', fn (Builder $q) => $q->where('designation_id', $data['value']));
                        }
                    }),

                SelectFilter::make('teacher_status')
                    ->label('Teacher Status')
                    ->options(\App\Models\EmploymentStatus::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['value'])) {
                            $query->whereHas('teacher', fn (Builder $q) => $q->where('employment_status_id', $data['value']));
                        }
                    }),

                SelectFilter::make('job_type_id')
                    ->label('Job Type')
                    ->relationship('jobType', 'name'),

                TrashedFilter::make(),
            ],layout: FiltersLayout::Modal)
            ->filtersTriggerAction(function ($action) {
                return $action->slideOver();
            })
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
