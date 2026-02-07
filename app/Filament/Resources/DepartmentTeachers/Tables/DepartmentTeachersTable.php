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

                TextColumn::make('teacher.first_name')
                    ->label('Teacher Name')
                    ->formatStateUsing(fn ($record) => $record->teacher->first_name . ' ' . $record->teacher->last_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

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
                            ->afterStateUpdated(fn ($set) => $set('department_id', null))
                            ->default(function() use ($adminRole) {
                                 if (!$adminRole || !$adminRole->pivot) return null;
                                 if ($adminRole->pivot->faculty_id) {
                                     return $adminRole->pivot->faculty_id;
                                 } 
                                 return null;
                            }),

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
                            ->preload()
                            ->default($adminRole && $adminRole->pivot ? $adminRole->pivot->department_id : null),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['faculty_id'] ?? null,
                                fn (Builder $query, $id) => $query->whereHas('department', fn ($q) => $q->where('faculty_id', $id))
                            )
                            ->when(
                                $data['department_id'] ?? null,
                                fn (Builder $query, $id) => $query->where('department_id', $id)
                            );
                    }),

                SelectFilter::make('job_type_id')
                    ->label('Job Type')
                    ->relationship('jobType', 'name'),

                TrashedFilter::make(),
            ])
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
