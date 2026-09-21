<?php

namespace App\Filament\Resources\NamePrefixes\Tables;

use App\Models\NamePrefix;
use App\Services\NameAffixMerger;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class NamePrefixesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Prefix')
                    ->searchable()
                    ->sortable(),

                /*
                 * How many people actually use it. This is the column that
                 * makes the list worth reading: it separates the titles the
                 * faculty really holds from the long tail, and it is what shows
                 * whether an odd-looking row like "Ms. Mst." is a mistake on two
                 * profiles or a title somebody genuinely uses.
                 *
                 * counts() adds a subquery to the listing rather than loading
                 * every teacher to call count() on them.
                 */
                TextColumn::make('teachers_count')
                    ->counts('teachers')
                    ->label('Teachers')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'gray' : 'success')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Active Only')
                    ->query(fn ($query) => $query->where('is_active', true)),

                Filter::make('unused')
                    ->label('Not used by anybody')
                    ->query(fn ($query) => $query->doesntHave('teachers')),
            ])
            ->recordActions([
                EditAction::make(),

                /*
                 * Fold this title into another and be rid of it.
                 *
                 * This list came out of a database that wrote twelve titles
                 * twenty-five ways, and the import could only collapse the
                 * spellings it recognised — "Ms. Mst." and "Dr. Mst." arrived as
                 * real rows on six people. Without a merge the only way to tidy
                 * one away is to open every profile holding it, and the row
                 * cannot be deleted until they are all moved, because deleting
                 * it would leave those names with no title at all.
                 */
                Action::make('merge')
                    ->label('Merge into…')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('warning')
                    ->visible(fn (NamePrefix $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->modalHeading(fn (NamePrefix $record): string => "Merge \"{$record->name}\" into another prefix")
                    ->modalDescription('Everybody written with this prefix is moved to the one you choose, and this one is then deleted. Nobody\'s name is changed.')
                    ->modalSubmitActionLabel('Merge')
                    ->form(fn (NamePrefix $record): array => [
                        Select::make('into')
                            ->label('Merge into')
                            ->options(static::otherPrefixes($record))
                            ->required()
                            ->searchable()
                            ->live()
                            ->columnSpanFull(),

                        Placeholder::make('effect')
                            ->label('What this does')
                            ->columnSpanFull()
                            ->content(function (Get $get) use ($record): HtmlString {
                                $target = $get('into') ? NamePrefix::find($get('into')) : null;

                                if (! $target) {
                                    return new HtmlString('Choose a prefix above.');
                                }

                                $count = app(NameAffixMerger::class)->preview($record, $target)['moving'];

                                return new HtmlString(
                                    '<strong>' . number_format($count) . ' teacher(s)</strong> move from "'
                                    . e($record->name) . '" to "' . e($target->name) . '".<br>'
                                    . '"' . e($record->name) . '" is then deleted. This cannot be undone from here — '
                                    . 'putting it back means recreating the prefix and setting it on each of them again.'
                                );
                            }),
                    ])
                    ->action(function (NamePrefix $record, array $data): void {
                        $target = NamePrefix::find($data['into']);

                        if (! $target || $target->is($record)) {
                            Notification::make()->danger()->title('Nothing to merge into')->send();

                            return;
                        }

                        $name = $record->name;
                        $moved = app(NameAffixMerger::class)->mergePrefix($record, $target);

                        activity('name-affix')
                            ->causedBy(auth()->user())
                            ->withProperties(['from' => $name, 'into' => $target->name, 'teachers' => $moved])
                            ->event('merge')
                            ->log('merged a name prefix');

                        Notification::make()
                            ->success()
                            ->title('Merged')
                            ->body(number_format($moved) . ' teacher(s) moved from "' . $name . '" to "' . $target->name . '", and "' . $name . '" was deleted.')
                            ->send();
                    }),

                /*
                 * Deleting one nulls the column on everybody holding it — the
                 * foreign key is nullOnDelete — so the warning says so rather
                 * than letting somebody find out afterwards.
                 */
                DeleteAction::make()
                    ->modalDescription(fn ($record): string => $record->teachers()->count() > 0
                        ? 'This prefix is on ' . $record->teachers()->count()
                            . ' teacher(s). Deleting it leaves their names without a title; the names themselves are not touched.'
                        : 'Nobody is using this prefix.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    /*
                     * Several at once, because the spellings arrive in families:
                     * "Prof. Dr.", "Professor Dr" and "Prof.Dr." are all the same
                     * title, and tidying them one merge at a time is three trips
                     * through the same dialog.
                     */
                    BulkAction::make('merge_selected')
                        ->label('Merge selected into…')
                        ->icon('heroicon-o-arrows-pointing-in')
                        ->color('warning')
                        ->modalHeading('Merge the selected prefixes into one')
                        ->modalDescription('Everybody written with the selected prefixes is moved to the one you choose, and the others are deleted.')
                        ->modalSubmitActionLabel('Merge')
                        ->form([
                            Select::make('into')
                                ->label('Keep this one')
                                ->options(fn (): array => NamePrefix::query()
                                    ->orderBy('sort_order')->pluck('name', 'id')->all())
                                ->required()
                                ->searchable()
                                ->live()
                                ->helperText('The survivor. It may be one of the ones you selected — it is simply left alone.')
                                ->columnSpanFull(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $target = NamePrefix::find($data['into']);

                            if (! $target) {
                                Notification::make()->danger()->title('Nothing to merge into')->send();

                                return;
                            }

                            $merger = app(NameAffixMerger::class);
                            $moved = 0;
                            $gone = [];

                            foreach ($records as $record) {
                                // The survivor is skipped rather than refused, so
                                // selecting it along with the rest is harmless.
                                if ($record->is($target)) {
                                    continue;
                                }

                                $gone[] = $record->name;
                                $moved += $merger->mergePrefix($record, $target);
                            }

                            if ($gone === []) {
                                Notification::make()
                                    ->warning()
                                    ->title('Nothing to do')
                                    ->body('The only prefix selected was the one you chose to keep.')
                                    ->send();

                                return;
                            }

                            activity('name-affix')
                                ->causedBy(auth()->user())
                                ->withProperties(['from' => $gone, 'into' => $target->name, 'teachers' => $moved])
                                ->event('merge')
                                ->log('merged name prefixes');

                            Notification::make()
                                ->success()
                                ->title('Merged')
                                ->body(number_format($moved) . ' teacher(s) moved to "' . $target->name . '". Deleted: ' . implode(', ', $gone) . '.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Every prefix except this one, for the merge target list.
     *
     * The count goes in the label so the choice can be made without leaving the
     * dialog to go and look — merging a title used by 911 people into one used
     * by four is almost certainly the wrong way round.
     *
     * @return array<int, string>
     */
    protected static function otherPrefixes(NamePrefix $record): array
    {
        return NamePrefix::query()
            ->whereKeyNot($record->getKey())
            ->withCount('teachers')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (NamePrefix $p): array => [
                $p->id => $p->name . ' (' . number_format($p->teachers_count) . ')',
            ])
            ->all();
    }
}
