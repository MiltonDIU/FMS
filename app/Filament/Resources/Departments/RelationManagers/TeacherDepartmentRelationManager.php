<?php

namespace App\Filament\Resources\Departments\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeacherDepartmentRelationManager extends RelationManager
{
    protected static string $relationship = 'teachersViaAssignment';



    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where(function ($q) {
                $q->where('teachers.department_id', '!=', $this->getOwnerRecord()->id)
                    ->orWhereNull('teachers.department_id');
            }))
            ->columns([
                TextColumn::make('employee_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('webpage')
                    ->label('Profile Slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('designation.name')
                    ->label('Designation')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('employmentStatus.name')
                    ->badge()
                    ->color(fn ($record) => $record->employmentStatus?->color ?? 'gray')
                    ->label('Status')
                    ->sortable(),
                TextColumn::make('jobType.name')
                    ->badge()
                    ->color('info')
                    ->label('Job Type')
                    ->sortable(),
                TextColumn::make('profile_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'draft' => 'gray',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('joining_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
