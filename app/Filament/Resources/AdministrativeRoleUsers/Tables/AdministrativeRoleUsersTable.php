<?php

namespace App\Filament\Resources\AdministrativeRoleUsers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AdministrativeRoleUsersTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();
        $adminRoleUser = null;
        
        if (!$user->hasRole('super_admin')) {
            $adminRoleUser = $user->administrativeRoles()
                ->wherePivot('is_active', true)
                ->whereNull('administrative_role_user.end_date')
                ->first();
        }

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('administrativeRole.name')
                    ->label('Role')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('faculty.name')
                    ->label('Faculty')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable()
                    ->placeholder('Ongoing'),

                IconColumn::make('is_acting')
                    ->label('Acting')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('assignedBy.name')
                    ->label('Assigned By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('administrative_role_id')
                    ->label('Role')
                    ->relationship('administrativeRole', 'name'),

                SelectFilter::make('faculty_id')
                    ->label('Faculty')
                    ->relationship('faculty', 'name', function ($query) use ($adminRoleUser) {
                        if (!$adminRoleUser) return;
                        
                         if ($adminRoleUser->pivot->faculty_id) {
                             $query->where('id', $adminRoleUser->pivot->faculty_id);
                         } elseif ($adminRoleUser->pivot->department_id) {
                             $department = \App\Models\Department::find($adminRoleUser->pivot->department_id);
                             if ($department) {
                                  $query->where('id', $department->faculty_id);
                             }
                         }
                    })
                    ->default(function() use ($adminRoleUser) {
                         if (!$adminRoleUser || !$adminRoleUser->pivot) return null;
                         
                         // Only set default if user is explicitly strictly Faculty-scoped
                         if ($adminRoleUser->pivot->faculty_id) {
                             return $adminRoleUser->pivot->faculty_id;
                         } 
                         
                         // For department users, do NOT set a faculty default. 
                         // Their records typically have faculty_id = null (linked to dept only).
                         // Setting this filter hides their own department records.
                         return null;
                    }),

                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name', function ($query) use ($adminRoleUser) {
                        if (!$adminRoleUser) return;

                        if ($adminRoleUser->pivot->department_id) {
                            $query->where('id', $adminRoleUser->pivot->department_id);
                        } elseif ($adminRoleUser->pivot->faculty_id) {
                            $query->where('faculty_id', $adminRoleUser->pivot->faculty_id);
                        }
                    })
                    ->default($adminRoleUser && $adminRoleUser->pivot ? $adminRoleUser->pivot->department_id : null),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),

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
