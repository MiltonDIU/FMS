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
                /*
                 * Where the export said this person was writing from.
                 *
                 * Every row in this table used to look the same — a GA with a
                 * placeholder address — so a co-author at Universiti Malaysia
                 * Pahang and one of our own teachers under a misspelt name were
                 * indistinguishable. This is the difference, and it is the
                 * difference that decides whether a row is a merge waiting to
                 * happen or somebody who belongs here permanently.
                 */
                TextColumn::make('used_our_affiliation')
                    ->label('Affiliation')
                    ->badge()
                    ->state(fn ($record) => match ($record->used_our_affiliation) {
                        true => 'Wrote as ' . \App\Helpers\Institution::shortName(),
                        false => 'Another institution',
                        default => 'Not established',
                    })
                    ->color(fn ($record) => match ($record->used_our_affiliation) {
                        true => 'warning',
                        false => 'gray',
                        default => 'secondary',
                    })
                    /*
                     * Named underneath, so "not ours" is answerable without
                     * opening the record or going back to the export.
                     *
                     * Every institution the papers put them at, not one. This
                     * used to read a column on the author holding whichever was
                     * seen first, which on somebody who has moved — or who
                     * collaborates across two — named one place and implied it
                     * was the only one.
                     */
                    ->description(function ($record) {
                        $affiliations = $record->affiliations();

                        if ($affiliations->isEmpty()) {
                            return null;
                        }

                        return $affiliations->take(2)->implode('; ')
                            . ($affiliations->count() > 2 ? ' +' . ($affiliations->count() - 2) . ' more' : '');
                    })
                    ->sortable(),
                /*
                 * Who this name turned out to be.
                 *
                 * Empty for everyone genuinely external, which is most of them.
                 * A filled cell means the papers below have already been handed
                 * to that teacher's profile — which is the only way to tell a
                 * name that has been dealt with from one nobody has looked at.
                 */
                TextColumn::make('mergedIntoTeacher.full_name')
                    ->label('Merged Into')
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->description(fn ($record) => $record->merged_at?->format('M d, Y'))
                    ->url(fn ($record) => $record->merged_into_teacher_id
                        ? \App\Filament\Resources\Teachers\TeacherResource::getUrl('edit', ['record' => $record->merged_into_teacher_id])
                        : null)
                    ->searchable(query: fn (\Illuminate\Database\Eloquent\Builder $query, string $search) => $query
                        ->whereHas('mergedIntoTeacher', fn ($q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"))),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('merged')
                    ->label('Merged into a teacher')
                    ->placeholder('All')
                    ->trueLabel('Yes — already handed over')
                    ->falseLabel('No — still an external name')
                    ->queries(
                        true: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->merged(),
                        false: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->notMerged(),
                        blank: fn (\Illuminate\Database\Eloquent\Builder $query) => $query,
                    ),
                /*
                 * The question this column exists to answer: which of these
                 * 7,347 names are actually ours. Somebody who wrote under our
                 * own affiliation and is not a teacher is either a teacher the
                 * matcher could not place by name, or a student or member of
                 * staff who is not in the teachers table at all — and both are
                 * worth looking at. Nobody needs to look at the other 4,804.
                 */
                \Filament\Tables\Filters\TernaryFilter::make('used_our_affiliation')
                    ->label('Wrote under our affiliation')
                    ->placeholder('All')
                    ->trueLabel('Yes — may be one of ours')
                    ->falseLabel('No — another institution')
                    ->queries(
                        true: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('used_our_affiliation', true),
                        false: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->where('used_our_affiliation', false),
                        blank: fn (\Illuminate\Database\Eloquent\Builder $query) => $query,
                    ),
                \Filament\Tables\Filters\Filter::make('possibly_ours')
                    ->label('Ours, and not yet merged')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->possiblyOurs()),
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
                    /*
                     * The one that moves papers onto a teacher's profile.
                     *
                     * Distinct from "Merge Selected Authors" below, which only
                     * folds duplicate external names into one another and never
                     * touches authorable_type. This one changes the type as well
                     * as the id, so the publication stops belonging to an
                     * outside name and starts belonging to one of our teachers.
                     */
                    BulkAction::make('merge_into_teacher')
                        ->label('Merge into Teacher')
                        ->icon('heroicon-o-academic-cap')
                        ->color('success')
                        ->modalHeading('Hand these publications to a teacher')
                        ->modalDescription('Every publication credited to the selected authors moves to the teacher you pick, keeping its role and incentive. The author records are kept and marked as merged.')
                        ->modalSubmitActionLabel('Merge')
                        // Rewrites ownership and money, so it asks for the same
                        // permission as removing an author outright.
                        ->visible(fn (): bool => auth()->user()?->can('Delete:Author') ?? false)
                        ->form(fn (\Illuminate\Support\Collection $records) => [
                            \Filament\Forms\Components\Placeholder::make('selected')
                                ->label('Authors being merged')
                                ->content(fn () => $records->pluck('name')->implode(', ')),

                            \Filament\Forms\Components\Select::make('teacher_id')
                                ->label('Merge into this teacher')
                                ->required()
                                ->searchable()
                                // Searched in the database rather than preloaded:
                                // there are 2,000 teachers, and full_name is a
                                // column that is empty on every row — the name
                                // is built from the parts by an accessor.
                                ->getSearchResultsUsing(fn (string $search) => \App\Models\Teacher::query()
                                    ->where(fn ($q) => $q
                                        ->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('middle_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhere('employee_id', 'like', "%{$search}%"))
                                    ->orderBy('is_archived')
                                    ->orderBy('first_name')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($t) => [$t->id => $t->full_name
                                        . ($t->employee_id ? ' — ' . $t->employee_id : '')
                                        . ($t->is_archived ? ' (Former)' : '')])
                                    ->all())
                                ->getOptionLabelUsing(fn ($value) => \App\Models\Teacher::find($value)?->full_name)
                                ->helperText('Type a name or employee ID. Teachers who have left are offered too — they still co-author with the people who are here.'),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            $teacher = \App\Models\Teacher::find($data['teacher_id']);

                            if (! $teacher) {
                                \Filament\Notifications\Notification::make()
                                    ->title('That teacher no longer exists.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $publications = 0;
                            $combined = 0;
                            $amount = 0.0;
                            $skipped = [];

                            foreach ($records as $author) {
                                if ($author->isMerged()) {
                                    // Merging twice would move nothing and would
                                    // overwrite the record of where it went.
                                    $skipped[] = $author->name;

                                    continue;
                                }

                                $result = $author->mergeInto($teacher);

                                $publications += $result['publications'];
                                $combined += $result['combined'];
                                $amount += $result['amount'];
                            }

                            $body = "{$publications} publication(s) now belong to {$teacher->full_name}.";

                            if ($combined > 0) {
                                $body .= " {$combined} were already credited to them, so the entries were combined.";
                            }

                            if ($amount > 0) {
                                $body .= ' ৳' . number_format($amount, 2) . ' of incentive moved with them.';
                            }

                            if ($skipped) {
                                $body .= ' Skipped (already merged): ' . implode(', ', $skipped) . '.';
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Merged into teacher')
                                ->body($body)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),

                    BulkAction::make('merge')
                        ->label('Merge Selected Authors')
                        ->icon('heroicon-o-user-group')
                        ->color('warning')
                        // Rewrites foreign keys and deletes the merged-away
                        // rows, so it needs Delete:Author, not just list access.
                        ->visible(fn (): bool => auth()->user()?->can('Delete:Author') ?? false)
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
