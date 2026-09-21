<?php

namespace App\Filament\Resources\NamePrefixes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Who is written with this title.
 *
 * The count on the listing says how many; this says which. That matters most
 * for the rows that look like mistakes — "Ms. Mst." sits on four people and
 * "Dr. Mst." on two, and the only way to decide whether those are real or two
 * profiles somebody filled in twice is to open them and read the names.
 *
 * No create or attach action. A teacher's title belongs to the teacher and is
 * set on their own profile; reaching it from this side would be a second place
 * to change the same thing.
 */
class TeachersRelationManager extends RelationManager
{
    protected static string $relationship = 'teachers';

    protected static ?string $title = 'Teachers using this prefix';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->defaultSort('first_name')
            ->columns([
                TextColumn::make('employee_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('display_name')
                    ->label('Name')
                    // An accessor, so the database has nothing to search or
                    // sort on; these are the columns it is built from.
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),

                TextColumn::make('designation.name')
                    ->label('Designation')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
