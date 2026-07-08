<?php

namespace App\Filament\Resources\EducationalInstitutions\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class EducationalInstitutionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teachers_count')
                    ->label('Total Teachers')
                    ->counts('teachers')
                    ->sortable()
                    ->color('primary')
                    ->weight('bold')
                    ->action(
                        Action::make('viewLinkedTeachers')
                            ->modalHeading(fn ($record) => "Teachers linked to institution: {$record->name}")
                            ->modalContent(function ($record) {
                                $teachers = $record->teachers()->with(['department.faculty'])->get();
                                return view('filament.lookup.teachers-modal', ['teachers' => $teachers]);
                            })
                            ->modalSubmitAction(false)
                            ->modalWidth('4xl')
                    ),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('creator.full_name')
                    ->label('Created By')
                    ->placeholder('System')
                    ->sortable(),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('System')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
