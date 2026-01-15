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
                SelectFilter::make('faculty_id')
                    ->label('Faculty')
                    ->relationship('department.faculty', 'name', function ($query) use ($adminRole) {
                        if ($adminRole && $adminRole->pivot) {
                            if ($adminRole->pivot->faculty_id) {
                                // Faculty-scoped user
                                $query->where('id', $adminRole->pivot->faculty_id);
                            } elseif ($adminRole->pivot->department_id) {
                                // Department-scoped user: Find the faculty this department belongs to
                                $department = \App\Models\Department::find($adminRole->pivot->department_id);
                                if ($department) {
                                    $query->where('id', $department->faculty_id);
                                }
                            }
                        }
                    })
                    ->preload()
                    ->default(function() use ($adminRole) {
                         if (!$adminRole || !$adminRole->pivot) return null;

                         if ($adminRole->pivot->faculty_id) {
                             return $adminRole->pivot->faculty_id;
                         }

                         if ($adminRole->pivot->department_id) {
                              $department = \App\Models\Department::find($adminRole->pivot->department_id);
                              return $department ? $department->faculty_id : null;
                         }

                         return null;
                    }),

                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name', function ($query) use ($adminRole) {
                        if ($adminRole && $adminRole->pivot) {
                            if ($adminRole->pivot->department_id) {
                                $query->where('id', $adminRole->pivot->department_id);
                            } elseif ($adminRole->pivot->faculty_id) {
                                $query->where('faculty_id', $adminRole->pivot->faculty_id);
                            }
                        }
                    })
                    ->preload()
                    ->default($adminRole && $adminRole->pivot ? $adminRole->pivot->department_id : null),

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
