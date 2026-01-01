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
                    ->wrap(),

                TextColumn::make('approved_sections')
                    ->label('Approved')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::headline($state))
                    ->color(fn () => collect(['success', 'warning', 'info', 'danger', 'primary'])->random())
                    ->wrap(),

                TextColumn::make('rejected_sections')
                    ->label('Rejected')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::headline($state))
                    ->color(fn () => collect(['success', 'warning', 'info', 'danger', 'primary'])->random())
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
}

