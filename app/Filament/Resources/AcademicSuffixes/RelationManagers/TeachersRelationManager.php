<?php

namespace App\Filament\Resources\AcademicSuffixes\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Who writes this qualification after their name.
 *
 * Short lists by nature — sixteen people hold a PhD on file and one an MBA —
 * which is exactly why being able to see them is useful: a list this size can
 * be read and checked against what the faculty actually knows.
 *
 * No create or attach action, for the same reason as the prefixes: a teacher's
 * qualifications belong on their own profile, and a second place to set them
 * is a second place for them to disagree.
 */
class TeachersRelationManager extends RelationManager
{
    protected static string $relationship = 'teachers';

    protected static ?string $title = 'Teachers using this suffix';

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
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),

                TextColumn::make('namePrefix.name')
                    ->label('Prefix')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),

                TextColumn::make('designation.name')
                    ->label('Designation')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
