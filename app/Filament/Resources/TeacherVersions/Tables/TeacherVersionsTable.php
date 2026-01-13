<?php

namespace App\Filament\Resources\TeacherVersions\Tables;

use App\Services\TeacherVersionService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Group;
use Filament\Actions\Action as FormAction;

class TeacherVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('teacher.first_name')
                    ->label('Teacher')
                    ->formatStateUsing(fn ($record) => $record->teacher?->first_name . ' ' . $record->teacher?->last_name)
                    ->searchable(),
                TextColumn::make('version_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'partially_approved' => 'info',
                        'approved' => 'success',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                // Section status columns
                TextColumn::make('pending_sections')
                    ->label('Pending')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::headline($state))
                    ->color(fn () => collect(['success', 'warning', 'info', 'danger', 'primary'])->random())
                    ->action(Action::make('compare_pending')
                        ->form(fn ($record) => self::getComparisonFormSchema($record, $record->pending_sections ?? [], true))
                        ->modalHeading('Pending Changes Comparison')
                        ->modalWidth('7xl')
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false)
                    )
                    ->wrap(),

                TextColumn::make('approved_sections')
                    ->label('Approved')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::headline($state))
                    ->color(fn () => collect(['success', 'warning', 'info', 'danger', 'primary'])->random())
                    ->action(Action::make('compare_approved')
                        ->form(fn ($record) => self::getComparisonFormSchema($record, $record->approved_sections ?? [], false))
                        ->modalHeading('Approved Sections Data')
                        ->modalWidth('7xl')
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false)
                    )
                    ->wrap(),

                TextColumn::make('rejected_sections')
                    ->label('Rejected')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::headline($state))
                    ->color(fn () => collect(['success', 'warning', 'info', 'danger', 'primary'])->random())
                    ->action(Action::make('compare_rejected')
                        ->form(fn ($record) => self::getComparisonFormSchema($record, $record->rejected_sections ?? [], false))
                        ->modalHeading('Rejected Sections Data')
                        ->modalWidth('7xl')
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false)
                    )
                    ->wrap(),

                TextColumn::make('change_summary')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::headline(
                        trim(str_replace('Updated sections: ', '', $state ?? ''))
                    ))
                    ->color(fn () => collect(['success', 'warning', 'info', 'danger', 'primary'])->random())
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('submittedBy.name')
                    ->label('Submitted By')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'partially_approved' => 'Partially Approved',
                        'approved' => 'Approved',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordUrl(null) // Row click disable করা হলো
            ->recordAction(null) // Row click action disable করা হলো
            ->recordActions([
                EditAction::make(),

                // Section-Level Approve Action
                Action::make('approve_sections')
                    ->label('Approve Sections')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => !empty($record->pending_sections))
                    ->modalHeading('Approve Sections')
                    ->modalDescription(fn ($record) => 'Select sections to approve. Data will be applied immediately.')
                    ->form(fn ($record) => [
                        \Filament\Forms\Components\CheckboxList::make('sections')
                            ->label('Pending Sections')
                            ->options(function () use ($record) {
                                $service = app(TeacherVersionService::class);
                                $user = auth()->user();
                                $pending = $record->pending_sections ?? [];

                                $options = [];
                                foreach ($pending as $section) {
                                    if ($service->canUserApproveSection($user, $section)) {
                                        $options[$section] = ucwords(str_replace('_', ' ', $section));
                                    }
                                }
                                return $options;
                            })
                            ->required()
                            ->columns(2)
                            ->validationMessages([
                                'required' => 'You must select at least one section you are authorized to approve.',
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(TeacherVersionService::class);
                        foreach ($data['sections'] as $section) {
                            $service->approveSection($record, $section);
                        }
                        Notification::make()
                            ->success()
                            ->title('Sections Approved')
                            ->body(count($data['sections']) . ' section(s) approved and applied.')
                            ->send();
                    }),

                // Section-Level Reject Action
                Action::make('reject_sections')
                    ->label('Reject Sections')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => !empty($record->pending_sections))
                    ->modalHeading('Reject Sections')
                    ->form(fn ($record) => [
                        \Filament\Forms\Components\CheckboxList::make('sections')
                            ->label('Select Sections to Reject')
                            ->options(function () use ($record) {
                                $service = app(TeacherVersionService::class);
                                $user = auth()->user();
                                $pending = $record->pending_sections ?? [];

                                $options = [];
                                foreach ($pending as $section) {
                                    if ($service->canUserApproveSection($user, $section)) {
                                        $options[$section] = ucwords(str_replace('_', ' ', $section));
                                    }
                                }
                                return $options;
                            })
                            ->required()
                            ->columns(2)
                            ->validationMessages([
                                'required' => 'You must select at least one section you are authorized to reject.',
                            ]),
                        \Filament\Forms\Components\Textarea::make('remarks')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(TeacherVersionService::class);
                        foreach ($data['sections'] as $section) {
                            $service->rejectSection($record, $section, $data['remarks']);
                        }
                        Notification::make()
                            ->success()
                            ->title('Sections Rejected')
                            ->body(count($data['sections']) . ' section(s) rejected.')
                            ->send();
                    }),

                // Approve All - for pending versions (legacy)
                Action::make('approve_all')
                    ->label('Approve All')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approve All Sections')
                    ->modalDescription('This will approve ALL pending sections at once.')
                    ->action(function ($record) {
                        app(TeacherVersionService::class)->approveVersion($record);
                        Notification::make()
                            ->success()
                            ->title('All Sections Approved')
                            ->body('Teacher profile has been fully updated.')
                            ->send();
                    }),

                // Reject All - for pending versions (legacy)
                Action::make('reject_all')
                    ->label('Reject All')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Reject All Sections')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('remarks')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        app(TeacherVersionService::class)->rejectVersion($record, $data['remarks']);
                        Notification::make()
                            ->success()
                            ->title('All Sections Rejected')
                            ->body('The teacher has been notified.')
                            ->send();
                    }),

                // Activate Action - for rollback
                Action::make('activate')
                    ->label('Rollback')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn ($record) => in_array($record->status, ['approved', 'partially_approved', 'completed']) && !$record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Activate Version (Rollback)')
                    ->modalDescription('This will restore the teacher profile to this version\'s COMPLETE state. All data from this version will be applied.')
                    ->action(function ($record) {
                        app(TeacherVersionService::class)->activateVersion($record);
                        Notification::make()
                            ->success()
                            ->title('Version Activated')
                            ->body('Teacher profile has been restored to this version.')
                            ->send();
                    }),

                // Inline Actions for Comparison Modal
                Action::make('approve_single_section')
                    ->label('Approve Section')
                    ->hidden()
                    ->requiresConfirmation()
                    ->modalHeading('Approve Section')
                    ->modalDescription(fn ($arguments) => "Approve changes for " . ucwords(str_replace('_', ' ', $arguments['section'] ?? 'this section')) . "?")
                    ->action(function ($record, array $arguments) {
                        $section = $arguments['section'];
                        app(TeacherVersionService::class)->approveSection($record, $section);

                        Notification::make()->success()->title('Section Approved')->send();
                    }),

                Action::make('reject_single_section')
                    ->label('Reject Section')
                    ->hidden()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('remarks')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data, array $arguments) {
                        $section = $arguments['section'];
                        app(TeacherVersionService::class)->rejectSection($record, $section, $data['remarks']);

                        Notification::make()->success()->title('Section Rejected')->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function getComparisonFormSchema(\App\Models\TeacherVersion $record, array $sections, bool $showActions = true): array
    {
        $schema = [];
        $comparisons = self::getComparisonData($record, $sections);
        $service = app(TeacherVersionService::class);
        $user = auth()->user();

        foreach ($comparisons as $section => $data) {
            $isRelation = ($data['type'] ?? 'scalar') === 'relation';

            // Check permission for this section
            // User requested to completely HIDE the section if they don't have approval permission
            if (!$service->canUserApproveSection($user, $section)) {
                continue;
            }

            // Calculate Change Summary for the Header
            $changeSummary = '';

            if ($isRelation) {
                $items = $data['items'] ?? [];
                $newCount = collect($items)->where('status', 'new')->count();
                $modCount = collect($items)->where('status', 'modified')->count();
                $delCount = collect($items)->where('status', 'deleted')->count();

                $parts = [];
                if ($newCount) $parts[] = "$newCount New";
                if ($modCount) $parts[] = "$modCount Modified";
                if ($delCount) $parts[] = "$delCount Deleted";

                $changeSummary = 'Changes: ' . (empty($parts) ? 'No Changes' : implode(', ', $parts));
            } else {
                $oldStats = $data['old'] ?? [];

                // Heuristic for "New Section" vs "Modified Section"
                // If old data is largely empty, it's New.
                $oldEmpty = empty(array_filter($oldStats, fn($v) => !is_null($v) && $v !== ''));

                if ($oldEmpty) {
                    $changeSummary = 'Status: NEW SECTION';
                } else {
                    $changeSummary = 'Status: UPDATED';
                }
            }

            $sectionComponent = Section::make(\Illuminate\Support\Str::headline($section))
                ->description($changeSummary)
                ->schema(function() use ($data, $isRelation, $section) {
                    if ($isRelation) {
                        return self::getRelationSchema($data['items'], $section);
                    }
                    return self::getScalarSchema($data['old'], $data['new'], $section);
                })
                ->collapsible();

            // Only show actions if explicitly requested AND user has permission
            if ($showActions) {
                $sectionComponent->headerActions([
                    FormAction::make('approve_section_' . $section)
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn() => app(TeacherVersionService::class)->approveSection($record, $section)),
                    FormAction::make('reject_section_' . $section)
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('remarks')->required()
                        ])
                        ->action(fn(array $data) => app(TeacherVersionService::class)->rejectSection($record, $section, $data['remarks'])),
                ]);
            } else {
                // Optional: visual indicator that "Action Disabled / Unauthorized" or just strict view only.
                // User requirement implies just handling the "showing" of the button.
                // If unauthorized, buttons are hidden.
            }

            $schema[] = $sectionComponent;
        }
        return $schema;
    }

    protected static function getScalarSchema(array $old, array $new, string $uniqueId = ''): array
    {
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        // Filter out system keys
        $keys = array_filter($keys, fn($k) => !in_array($k, ['id', 'created_at', 'updated_at', 'teacher_id']));

        $fields = [];
        $fields[] = Grid::make(2)->schema([
            Placeholder::make('lbl_old_' . $uniqueId)->label('Current Data')->content(''),
            Placeholder::make('lbl_new_' . $uniqueId)->label('Proposed Changes')->content(''),
        ]);

        foreach ($keys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            // Determine style for Old Data
            // If data is changed (and not just null->null), highlight old data
            $hasChanged = $oldVal !== $newVal;

            $oldStyle = 'background-color: #f9fafb; color: #4b5563;';
            if ($hasChanged && !is_null($oldVal)) {
                $oldStyle = 'background-color: #fef2f2; color: #ef4444; text-decoration: line-through;';
            }

            $fields[] = Grid::make(2)->schema([
                TextInput::make('old_' . $key . '_' . $uniqueId)
                    ->label(\Illuminate\Support\Str::headline($key))
                    ->default(is_array($oldVal) ? json_encode($oldVal) : $oldVal)
                    ->disabled()
                    ->extraInputAttributes(['style' => $oldStyle]),

                TextInput::make('new_' . $key . '_' . $uniqueId)
                    ->label(\Illuminate\Support\Str::headline($key))
                    ->default(is_array($newVal) ? json_encode($newVal) : $newVal)
                    ->disabled()
                    ->extraInputAttributes(['style' => 'background-color: #ffffff; border-color: #93c5fd; color: #111827; box-shadow: 0 0 0 1px #eff6ff;']),
            ]);
        }
        return $fields;
    }

    protected static function getRelationSchema(array $items, string $sectionKey): array
    {
        $components = [];
        foreach ($items as $index => $item) {
            $status = $item['status'];
            $color = match($status) {
                'new' => 'success',
                'modified' => 'warning',
                'deleted' => 'danger',
                default => 'gray'
            };

            $uniqueId = $sectionKey . '_item_' . $index;

            $components[] = Section::make("Item #" . ($index + 1) . " (" . strtoupper($status) . ")")
                ->description(function() use ($item) {
                    $val = !empty($item['new']) ? $item['new'] : $item['old'];
                    return $val['institution'] ?? $val['name'] ?? $val['title'] ?? '-';
                })
                ->schema(self::getScalarSchema($item['old'], $item['new'], $uniqueId))
                ->collapsible()
                ->collapsed(true)
                ->extraAttributes(['class' => "border-l-4 border-l-{$color}-500"]);
        }
        return $components;
    }

    protected static function getComparisonData(\App\Models\TeacherVersion $record, array $sections): array
    {
        $data = [];
        $teacher = $record->teacher;

        foreach ($sections as $section) {
            $oldData = self::getOldSectionData($teacher, $section);
            $newData = self::getNewSectionData($record, $section);

            // Check if this is a relational list (indexed array)
            // Relational data usually comes as array of arrays
            $isRelation = !empty($newData) && array_keys($newData) === range(0, count($newData) - 1);
            if (empty($newData) && !empty($oldData) && array_keys($oldData) === range(0, count($oldData) - 1)) {
                $isRelation = true;
            }

            if ($isRelation) {
                // Pair items by ID for better comparison
                $processed = self::pairRelationItems($oldData, $newData);
                $data[$section] = [
                    'type' => 'relation',
                    'items' => $processed
                ];
            } else {
                $data[$section] = [
                    'type' => 'scalar',
                    'old' => $oldData,
                    'new' => $newData
                ];
            }
        }

        return $data;
    }

    protected static function pairRelationItems(array $old, array $new): array
    {
        $items = [];
        $oldKeyed = collect($old)->keyBy('id')->all();
        $newKeyed = collect($new)->keyBy('id')->all();

        // Items in New Data (Modified or New)
        foreach ($new as $newItem) {
            $id = $newItem['id'] ?? null;
            if ($id && isset($oldKeyed[$id])) {
                // Exists in old -> Modified (or Unchanged)
                // We show it anyway to confirm state.
                $items[] = [
                    'status' => 'modified',
                    'old' => $oldKeyed[$id],
                    'new' => $newItem,
                    'id' => $id
                ];
                unset($oldKeyed[$id]);
            } else {
                // New Item
                $items[] = [
                    'status' => 'new',
                    'old' => [],
                    'new' => $newItem,
                    'id' => $id
                ];
            }
        }

        // Remaining Old items (Deleted)
        foreach ($oldKeyed as $id => $oldItem) {
            $items[] = [
                'status' => 'deleted',
                'old' => $oldItem,
                'new' => [], // Empty means deleted
                'id' => $id
            ];
        }

        return $items;
    }

    protected static function getOldSectionData(\App\Models\Teacher $teacher, string $section)
    {
        $map = TeacherVersionService::FIELD_SECTION_MAP[$section] ?? [];
        $relations = TeacherVersionService::RELATION_NAMES;

        $fields = $map;

        $relationFields = array_intersect($fields, $relations);

        if (!empty($relationFields)) {
            $relationName = reset($relationFields);
            if (method_exists($teacher, $relationName)) {
                $results = $teacher->$relationName()->get()->toArray();
                return $results;
            }
            return [];
        }

        $data = [];
        foreach ($fields as $field) {
            if (!in_array($field, TeacherVersionService::MEDIA_FIELDS)) {
                $data[$field] = $teacher->$field;
            }
        }
        return $data;
    }

    protected static function getNewSectionData(\App\Models\TeacherVersion $version, string $section)
    {
        $map = TeacherVersionService::FIELD_SECTION_MAP[$section] ?? [];
        $relations = TeacherVersionService::RELATION_NAMES;
        $versionData = $version->data ?? [];

        $fields = $map;
        $relationFields = array_intersect($fields, $relations);

        if (!empty($relationFields)) {
            $relationName = reset($relationFields);
            return $versionData[$relationName] ?? [];
        }

        $data = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $versionData) && !in_array($field, TeacherVersionService::MEDIA_FIELDS)) {
               $data[$field] = $versionData[$field];
            }
        }
        return $data;
    }
}

