<?php

namespace App\Filament\Resources\DepartmentTeachers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DepartmentTeacherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('teacher_id')
                        ->label('Teacher')
                        ->relationship('teacher', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name . ' (' . $record->employee_id . ')')
                        ->searchable(['first_name', 'last_name', 'employee_id'])
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set) => $set('existing_assignments', null)), // Trigger refresh

                    \Filament\Forms\Components\Placeholder::make('existing_assignments')
                        ->label('Current Assignments')
                        ->content(function (Get $get) {
                            $teacherId = $get('teacher_id');
                            if (!$teacherId) return 'Select a teacher to see assignments.';

                            $assignments = \App\Models\DepartmentTeacher::where('teacher_id', $teacherId)
                                ->with(['department', 'jobType', 'department.faculty'])
                                ->get();

                            if ($assignments->isEmpty()) return 'No existing department assignments.';

                            return $assignments->map(function ($assignment) {
                                return "• {$assignment->department->name} ({$assignment->department->faculty->name}) - " . ($assignment->jobType->name ?? 'No Job Type');
                            })->join('<br>');
                        })
                        ->hidden(fn (Get $get) => !$get('teacher_id'))
                        ->columnSpanFull(),

                    Select::make('department_id')
                        ->label('Department')
                        ->relationship('department', 'name', function ($query) {
                            $user = auth()->user();
                            $adminRole = $user->administrativeRoles()
                                ->wherePivot('is_active', true)
                                ->whereNull('administrative_role_user.end_date')
                                ->first();

                            if ($adminRole && $adminRole->pivot) {
                                if ($adminRole->pivot->department_id) {
                                    $query->where('id', $adminRole->pivot->department_id);
                                } elseif ($adminRole->pivot->faculty_id) {
                                    $query->where('faculty_id', $adminRole->pivot->faculty_id);
                                }
                            }
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('job_type_id')
                        ->label('Job Type')
                        ->relationship('jobType', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    TextInput::make('sort_order')
                        ->label('Sort Order')
                        ->numeric()
                        ->default(function (Get $get) {
                             $deptId = $get('department_id');
                             if (!$deptId) return 0;
                             $maxSort = \App\Models\DepartmentTeacher::where('department_id', $deptId)->max('sort_order');
                             return ($maxSort ?? 0) + 1;
                        })
                        ->required(),

                    \Filament\Forms\Components\Hidden::make('assigned_by')
                        ->default(auth()->id()),
                ]),
            ]);
    }
}
