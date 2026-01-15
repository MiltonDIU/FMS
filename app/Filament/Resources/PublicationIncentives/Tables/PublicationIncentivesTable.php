<?php

namespace App\Filament\Resources\PublicationIncentives\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PublicationIncentivesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')

            ->columns([
                TextColumn::make('publication.title')
                    ->label('Publication')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'paid' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('authors_count')
                    ->label('Authors')
                    ->state(fn($record) => $record->publication->teachers()->count()),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make()
                    ->mutateRecordDataUsing(function (array $data, \App\Models\PublicationIncentive $record): array {
                        $data['author_incentives'] = $record->publication->teachers
                            ->sortBy('pivot.sort_order')
                            ->map(fn($t) => [
                                'teacher_id' => $t->id,
                                'author_role' => $t->pivot->author_role,
                                'incentive_amount' => $t->pivot->incentive_amount ?? 0,
                            ])->toArray();
                        return $data;
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('faculty_department')
                    ->form([
                        \Filament\Forms\Components\Select::make('faculty_id')
                            ->label('Faculty')
                            ->options(\App\Models\Faculty::pluck('name', 'id'))
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('department_id', null)),
                        \Filament\Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->options(fn (Get $get) =>
                                $get('faculty_id')
                                    ? \App\Models\Department::where('faculty_id', $get('faculty_id'))->pluck('name', 'id')
                                    : \App\Models\Department::pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['faculty_id'],
                                fn ($query, $id) => $query->whereHas('publication.department', fn ($q) => $q->where('faculty_id', $id))
                            )
                            ->when(
                                $data['department_id'],
                                fn ($query, $id) => $query->whereHas('publication', fn ($q) => $q->where('department_id', $id))
                            );
                    }),


                \Filament\Tables\Filters\Filter::make('publication_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date_from'),
                        \Filament\Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn ($query, $date) => $query->whereHas('publication', fn($q) => $q->whereDate('publication_date', '>=', $date)),
                            )
                            ->when(
                                $data['date_until'],
                                fn ($query, $date) => $query->whereHas('publication', fn($q) => $q->whereDate('publication_date', '<=', $date)),
                            );
                    })
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
