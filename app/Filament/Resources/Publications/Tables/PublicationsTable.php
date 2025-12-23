<?php

namespace App\Filament\Resources\Publications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PublicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('authors_list')
                    ->label('Authors')
                    ->state(function ($record) {
                        return $record->teachers->sortBy(function ($teacher) {
                            $role = $teacher->pivot->author_role;
                            $order = $teacher->pivot->sort_order;
                            
                            // Priority: First (1), Corresponding (2), Co-Author (3)
                            $rolePriority = match ($role) {
                                'first' => 1,
                                'corresponding' => 2,
                                default => 3,
                            };
                            
                            return sprintf('%d-%04d', $rolePriority, $order);
                        })->map(function ($teacher) {
                            $roleLabel = match ($teacher->pivot->author_role) {
                                'first' => 'First Author',
                                'corresponding' => 'Corresponding',
                                'co_author' => 'Co-Author',
                                default => ucfirst($teacher->pivot->author_role),
                            };
                            
                            // Highlight First Author
                            $style = $teacher->pivot->author_role === 'first' ? 'font-weight: bold;' : '';
                            
                            $fullName = trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
                            $details = "ID: {$teacher->employee_id}";
                            if ($teacher->phone) {
                                $details .= " | PH: {$teacher->phone}";
                            }

                            return "
                                <div style='margin-bottom: 4px;'>
                                    <span style='{$style}'>{$fullName}</span> 
                                    <span class='text-gray-500 text-xs'>({$roleLabel})</span>
                                    <div class='text-xs text-gray-400'>{$details}</div>
                                </div>
                            ";
                        })->implode('');
                    })
                    ->html()
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                         return $query->whereHas('teachers', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('employee_id', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('type.name')
                    ->label('Type')
                    ->sortable(),
                TextColumn::make('journal_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('publication_year')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),
                IconColumn::make('is_featured')
                    ->boolean(),
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
