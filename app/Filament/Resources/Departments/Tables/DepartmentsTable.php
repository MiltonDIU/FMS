<?php

namespace App\Filament\Resources\Departments\Tables;

use App\Models\Department;
use App\Models\Faculty;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();
        $adminRole = null;

        if ($user && ! $user->hasRole('super_admin')) {
            $adminRole = $user->administrativeRoles()
                ->wherePivot('is_active', true)
                ->whereNull('administrative_role_user.end_date')
                ->first();
        }

        return $table
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),
                TextColumn::make('name')
                    ->label('Department Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('short_name')
                    ->label('Short')
                    ->searchable()
                    ->badge(),
                TextColumn::make('code')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('faculty.short_name')
                    ->label('Faculty')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('teachers_count')
                    ->label('Teachers')
                    ->getStateUsing(function ($record) {
                        $total = $record->teachers()->count();
                        $guest = $record->teachersViaAssignment()->where(function ($q) use ($record) {
                            $q->where('teachers.department_id', '!=', $record->id)
                                ->orWhereNull('teachers.department_id');
                        })->count();
                        return "Total = {$total}<br>Guest = {$guest}";
                    })
                    ->badge()
                    ->html()
                    ->color('success'),
                TextColumn::make('publications_count')
                    ->label('Publications')
                    ->counts('publications')
                    ->badge()
                    ->color('success'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('erp_id')
                    ->label('ERP ID')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('faculty_id')
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
                        return $query->pluck('short_name', 'id');
                    })
                    ->searchable(),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
                TrashedFilter::make(),
            ],layout: FiltersLayout::Modal)
            ->filtersTriggerAction(function ($action) {
                return $action->slideOver();
            })
            ->recordActions([
                ViewAction::make(),
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
