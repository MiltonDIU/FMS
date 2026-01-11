<?php

namespace App\Filament\Resources\Publications\Tables;

use App\Models\Publication;
use App\Models\PublicationIncentive;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                TextColumn::make('incentive.total_amount')
                    ->label('Incentive')
                    ->money('BDT')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('incentive.status')
                    ->label('Incentive Status')
                    ->badge()

                    ->color(fn(?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('faculty.name')
                    ->label('Faculty')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('journal_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('publication_date')
                    ->label('Pub. Date')
                    ->date()
                    ->sortable(),
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
                    ->boolean()
            ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('publication_date_range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('publication_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('publication_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From: ' . \Carbon\Carbon::parse($data['from'])->format('M d, Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until: ' . \Carbon\Carbon::parse($data['until'])->format('M d, Y');
                        }
                        return $indicators;
                    }),

                // Publication Status Filter
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label('Publication Status')
                    ->options([
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'draft' => 'Draft',
                        'rejected' => 'Rejected',
                    ])
                    ->multiple(),

                // Incentive Status Filter
                \Filament\Tables\Filters\SelectFilter::make('incentive_status')
                    ->label('Incentive Status')
                    ->options([
                        'paid' => 'Paid',
                        'approved' => 'Approved',
                        'pending' => 'Pending',
                        'none' => 'No Incentive',
                    ])
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = $data['values'] ?? [];
                        if (empty($values)) {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($values) {
                            if (in_array('none', $values)) {
                                $query->orWhereDoesntHave('incentive');
                            }

                            $statusValues = array_filter($values, fn($v) => $v !== 'none');
                            if (!empty($statusValues)) {
                                $query->orWhereHas('incentive', function (Builder $q) use ($statusValues) {
                                    $q->whereIn('status', $statusValues);
                                });
                            }
                        });
                    }),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('add_incentive')
                    ->label('Incentive')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn($record) => !$record->hasIncentive())
                    ->mountUsing(function ($form, $record) {
                        $authors = $record->teachers
                            ->sortBy('pivot.sort_order')
                            ->map(fn($t) => [
                                'teacher_id' => $t->id,
                                'teacher_name' => $t->full_name,
                                'author_role' => $t->pivot->author_role,
                                'incentive_amount' => 0,
                            ])->toArray();

                        $form->fill([
                            'author_incentives' => $authors,
                            'total_amount' => 0,
                            'status' => 'pending',
                        ]);
                    })
                    ->form([
                        Section::make('Author Incentives')
                            ->description('Enter incentive amount for each author.')
                            ->schema([
                                Repeater::make('author_incentives')
                                    ->label('')
                                    ->schema([
                                        \Filament\Forms\Components\Hidden::make('teacher_id'),
                                        TextInput::make('teacher_name')
                                            ->label('Author')
                                            ->disabled()
                                            ->dehydrated(false),
                                        Select::make('author_role')
                                            ->label('Role')
                                            ->options([
                                                'first' => '1st Author',
                                                'corresponding' => 'Corresponding',
                                                'co_author' => 'Co-Author',
                                            ])
                                            ->disabled()
                                            ->dehydrated(false),
                                        TextInput::make('incentive_amount')
                                            ->label('Amount (TK)')
                                            ->numeric()
                                            ->prefix('৳')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                $authors = $get('../../author_incentives') ?? [];
                                                $total = collect($authors)->sum('incentive_amount');
                                                $set('../../total_amount', $total);
                                            }),
                                    ])
                                    ->columns(3)
                                    ->reorderable(false)
                                    ->addable(false)
                                    ->deletable(false),
                            ]),
                        Section::make('Summary')
                            ->schema([
                                TextInput::make('total_amount')
                                    ->label('Total Amount (TK)')
                                    ->numeric()
                                    ->prefix('৳')
                                    ->required()
                                    ->live()
                                    ->helperText('Total = Sum of author amounts'),
                                Placeholder::make('validation')
                                    ->label('')
                                    ->content(function (Get $get) {
                                        $total = (float) ($get('total_amount') ?? 0);
                                        $authors = $get('author_incentives') ?? [];
                                        $sum = collect($authors)->sum('incentive_amount');

                                        if ($total == $sum) {
                                            return '✅ Total matches: ৳' . number_format($sum, 2);
                                        }
                                        return '❌ Mismatch! Sum: ৳' . number_format($sum, 2);
                                    }),
                                Select::make('status')
                                    ->options(['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid'])
                                    ->default('pending'),
                                Textarea::make('remarks')->rows(2),
                            ])->columns(2),
                    ])
                    ->action(function ($record, array $data) {
                        // Validate total matches sum
                        $sum = collect($data['author_incentives'])->sum('incentive_amount');
                        $total = (float) $data['total_amount'];

                        if (bccomp((string) $total, (string) $sum, 2) !== 0) {
                            Notification::make()
                                ->title('Validation Error')
                                ->body("Total (৳{$total}) must equal sum of authors (৳{$sum})")
                                ->danger()
                                ->send();
                            return;
                        }

                        // Create incentive record
                        $incentive = PublicationIncentive::create([
                            'publication_id' => $record->id,
                            'total_amount' => $data['total_amount'],
                            'status' => $data['status'],
                            'remarks' => $data['remarks'] ?? null,
                        ]);

                        // Update author incentive amounts in pivot
                        foreach ($data['author_incentives'] as $author) {
                            $record->teachers()->updateExistingPivot(
                                $author['teacher_id'],
                                ['incentive_amount' => $author['incentive_amount']]
                            );
                        }

                        Notification::make()
                            ->title('Incentive Added')
                            ->body("Total ৳" . number_format($total, 2) . " added successfully!")
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn($record) => "Add Incentive: {$record->title}")
                    ->modalWidth('4xl'),

                Action::make('view_incentive')
                    ->label('View Incentive')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->visible(fn($record) => $record->hasIncentive())
                    ->url(fn($record) => route('filament.admin.resources.publication-incentives.edit', $record->incentive)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(function (Collection $records): StreamedResponse {
                            return response()->streamDownload(function () use ($records) {
                                $handle = fopen('php://output', 'w');

                                // CSV Headers
                                fputcsv($handle, [
                                    'Publication ID',
                                    'Title',
                                    'Type',
                                    'Faculty',
                                    'Department',
                                    'Journal Name',
                                    'Publication Date',
                                    'Publication Year',
                                    'Status',
                                    'Incentive Total',
                                    'Incentive Status',
                                    'Author Type',
                                    'Author Name',
                                    'Author Employee ID',
                                    'Author Email',
                                    'Author Role',
                                    'Author Amount',
                                ]);

                                foreach ($records as $publication) {
                                    $publication->load(['teachers', 'incentive', 'type', 'faculty', 'department']);

                                    $authors = $publication->teachers->sortBy('pivot.sort_order');

                                    if ($authors->isEmpty()) {
                                        // Publication without authors
                                        fputcsv($handle, [
                                            $publication->id,
                                            $publication->title,
                                            $publication->type?->name,
                                            $publication->faculty?->name,
                                            $publication->department?->name,
                                            $publication->journal_name,
                                            $publication->publication_date?->format('Y-m-d'),
                                            $publication->publication_year,
                                            $publication->status,
                                            $publication->incentive?->total_amount,
                                            $publication->incentive?->status,
                                            '', '', '', '', '', '',
                                        ]);
                                    } else {
                                        foreach ($authors as $author) {
                                            $roleLabel = match ($author->pivot->author_role) {
                                                'first' => '1st Author',
                                                'corresponding' => 'Corresponding Author',
                                                'co_author' => 'Co-Author',
                                                default => $author->pivot->author_role,
                                            };

                                            fputcsv($handle, [
                                                $publication->id,
                                                $publication->title,
                                                $publication->type?->name,
                                                $publication->faculty?->name,
                                                $publication->department?->name,
                                                $publication->journal_name,
                                                $publication->publication_date?->format('Y-m-d'),
                                                $publication->publication_year,
                                                $publication->status,
                                                $publication->incentive?->total_amount,
                                                $publication->incentive?->status,
                                                'Teacher',
                                                $author->full_name,
                                                $author->employee_id,
                                                $author->user?->email ?? '',
                                                $roleLabel,
                                                $author->pivot->incentive_amount,
                                            ]);
                                        }
                                    }
                                }

                                fclose($handle);
                            }, 'publications_export_' . now()->format('Y-m-d_His') . '.csv');
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('export_all_background')
                    ->label('Export All (Background)')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->action(function ($livewire) {
                        $user = auth()->user();
                        $filters = $livewire->tableFilters;
                        $search = $livewire->tableSearch;

                        \App\Jobs\ExportPublicationsJob::dispatch($user, $filters, $search);

                        Notification::make()
                            ->title('Export Started')
                            ->body('We will notify you when the file is ready.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}

