<?php

namespace App\Filament\Resources\AcademicSuffixes\Tables;

use App\Models\AcademicSuffix;
use App\Services\NameAffixMerger;
use Filament\Actions\Action;
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
use Illuminate\Support\HtmlString;

class AcademicSuffixesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Suffix')
                    ->searchable()
                    ->sortable(),

                /*
                 * counts() across the pivot. Worth having on the list because
                 * this table is meant to grow one row at a time as people need
                 * them, and a row at zero after a few months is either a
                 * qualification nobody here holds or one nobody was told to
                 * pick — both worth noticing.
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
                 * Same idea as the prefixes, different plumbing: a suffix is a
                 * pivot row, so somebody can hold both the one being merged away
                 * and the one being kept. Those people are not given it twice —
                 * the pivot has a unique key — they simply lose the old row.
                 * The preview says how many that is, because "16 move" and
                 * "14 move, 2 already have it" are different things to agree to.
                 */
                Action::make('merge')
                    ->label('Merge into…')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('warning')
                    ->visible(fn (AcademicSuffix $record): bool => auth()->user()?->can('delete', $record) ?? false)
                    ->modalHeading(fn (AcademicSuffix $record): string => "Merge \"{$record->name}\" into another suffix")
                    ->modalDescription('Everybody holding this suffix is given the one you choose instead, and this one is then deleted.')
                    ->modalSubmitActionLabel('Merge')
                    ->form(fn (AcademicSuffix $record): array => [
                        Select::make('into')
                            ->label('Merge into')
                            ->options(static::otherSuffixes($record))
                            ->required()
                            ->searchable()
                            ->live()
                            ->columnSpanFull(),

                        Placeholder::make('effect')
                            ->label('What this does')
                            ->columnSpanFull()
                            ->content(function (Get $get) use ($record): HtmlString {
                                $target = $get('into') ? AcademicSuffix::find($get('into')) : null;

                                if (! $target) {
                                    return new HtmlString('Choose a suffix above.');
                                }

                                $p = app(NameAffixMerger::class)->preview($record, $target);

                                $lines = ['<strong>' . number_format($p['moving']) . ' teacher(s)</strong> are given "'
                                    . e($target->name) . '" in place of "' . e($record->name) . '".'];

                                if ($p['already'] > 0) {
                                    $lines[] = number_format($p['already'])
                                        . ' already hold both, so they simply lose "' . e($record->name) . '".';
                                }

                                $lines[] = '"' . e($record->name) . '" is then deleted.';

                                return new HtmlString(implode('<br>', $lines));
                            }),
                    ])
                    ->action(function (AcademicSuffix $record, array $data): void {
                        $target = AcademicSuffix::find($data['into']);

                        if (! $target || $target->is($record)) {
                            Notification::make()->danger()->title('Nothing to merge into')->send();

                            return;
                        }

                        $name = $record->name;
                        $moved = app(NameAffixMerger::class)->mergeSuffix($record, $target);

                        activity('name-affix')
                            ->causedBy(auth()->user())
                            ->withProperties(['from' => $name, 'into' => $target->name, 'teachers' => $moved])
                            ->event('merge')
                            ->log('merged an academic suffix');

                        Notification::make()
                            ->success()
                            ->title('Merged')
                            ->body(number_format($moved) . ' teacher(s) now hold "' . $target->name . '" instead of "' . $name . '", which was deleted.')
                            ->send();
                    }),

                // Deleting detaches it from everybody who holds it. The pivot
                // rows go; the teachers do not.
                DeleteAction::make()
                    ->modalDescription(fn ($record): string => $record->teachers()->count() > 0
                        ? 'This suffix is on ' . $record->teachers()->count()
                            . ' teacher(s). Deleting it takes it off their names.'
                        : 'Nobody is using this suffix.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Every suffix except this one, for the merge target list.
     *
     * @return array<int, string>
     */
    protected static function otherSuffixes(AcademicSuffix $record): array
    {
        return AcademicSuffix::query()
            ->whereKeyNot($record->getKey())
            ->withCount('teachers')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (AcademicSuffix $s): array => [
                $s->id => $s->name . ' (' . number_format($s->teachers_count) . ')',
            ])
            ->all();
    }
}
