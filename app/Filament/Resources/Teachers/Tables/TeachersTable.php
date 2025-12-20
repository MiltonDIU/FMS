<?php

namespace App\Filament\Resources\Teachers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TeachersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('department.name')
                    ->searchable(),
                TextColumn::make('designation.name')
                    ->searchable(),
                TextColumn::make('employee_id')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('middle_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('personal_phone')
                    ->searchable(),
                TextColumn::make('secondary_email')
                    ->searchable(),
                TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
                TextColumn::make('gender')
                    ->badge(),
                TextColumn::make('blood_group')
                    ->searchable(),
                TextColumn::make('nationality')
                    ->searchable(),
                TextColumn::make('religion')
                    ->searchable(),
                TextColumn::make('joining_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('work_location')
                    ->searchable(),
                TextColumn::make('office_room')
                    ->searchable(),
                TextColumn::make('photo')
                    ->searchable(),
                TextColumn::make('personal_website')
                    ->searchable(),
                TextColumn::make('google_scholar')
                    ->searchable(),
                TextColumn::make('research_gate')
                    ->searchable(),
                TextColumn::make('orcid')
                    ->searchable(),
                TextColumn::make('profile_status')
                    ->badge(),
                IconColumn::make('is_public')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
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
