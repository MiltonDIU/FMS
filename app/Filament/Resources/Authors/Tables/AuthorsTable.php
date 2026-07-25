<?php

namespace App\Filament\Resources\Authors\Tables;

use Filament\Actions\BulkAction;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('authorType.name')
                    ->label('Author Type')
                    ->sortable(),
                TextColumn::make('publications_count')
                    ->counts('publications')
                    ->label('Total Publications')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
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
                    BulkAction::make('merge')
                        ->label('Merge Selected Authors')
                        ->icon('heroicon-o-user-group')
                        ->color('warning')
                        ->form(fn (\Illuminate\Support\Collection $records) => [
                            \Filament\Forms\Components\Select::make('primary_author_id')
                                ->label('Select Primary Author (surviving record)')
                                ->options($records->pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $primaryId = $data['primary_author_id'];
                            $duplicateIds = $records->pluck('id')->diff([$primaryId])->toArray();

                            if (empty($duplicateIds)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Please select more than one author to merge.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            \Illuminate\Support\Facades\DB::transaction(function () use ($primaryId, $duplicateIds) {
                                // Process publication author link updates
                                $duplicateLinks = \Illuminate\Support\Facades\DB::table('publication_authors')
                                    ->where('authorable_type', 'App\Models\Author')
                                    ->whereIn('authorable_id', $duplicateIds)
                                    ->get();

                                foreach ($duplicateLinks as $link) {
                                    $exists = \Illuminate\Support\Facades\DB::table('publication_authors')
                                        ->where('publication_id', $link->publication_id)
                                        ->where('authorable_type', 'App\Models\Author')
                                        ->where('authorable_id', $primaryId)
                                        ->exists();

                                    if ($exists) {
                                        // Primary author already has this link, delete the duplicate link
                                        \Illuminate\Support\Facades\DB::table('publication_authors')
                                            ->where('id', $link->id)
                                            ->delete();
                                    } else {
                                        // Update the duplicate link to point to primary author
                                        \Illuminate\Support\Facades\DB::table('publication_authors')
                                            ->where('id', $link->id)
                                            ->update(['authorable_id' => $primaryId]);
                                    }
                                }

                                // Delete duplicate authors
                                \Illuminate\Support\Facades\DB::table('authors')
                                    ->whereIn('id', $duplicateIds)
                                    ->delete();
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Authors merged successfully.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                ]),
            ]);
    }
}
