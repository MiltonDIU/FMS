<?php

namespace App\Filament\Resources\Teachers\Actions;

use App\Models\Teacher;
use App\Services\TeacherErpComparisonService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;

class CompareTeacherErpAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'compare_erp';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Compare with ERP')
            ->icon('heroicon-o-arrows-right-left')
            ->color('info')
            ->modalHeading('DIU ERP Profile Comparison & Selective Import')
            ->modalDescription('Compare your profile against your records in the DIU ERP system, then selectively merge or override specific sections.')
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Import & Synchronize')
            ->visible(function ($livewire): bool {
                $teacher = static::resolveTeacher($livewire);
                return $teacher !== null && TeacherErpComparisonService::canAccess($teacher);
            })
            ->modalContent(function ($livewire) {
                $teacher = static::resolveTeacher($livewire);
                if (! $teacher) {
                    return view('filament.pages.partials.erp-comparison-modal', [
                        'error' => 'No teacher profile found.',
                    ]);
                }

                try {
                    $comparison = app(TeacherErpComparisonService::class)->compare($teacher);
                    return view('filament.pages.partials.erp-comparison-modal', [
                        'comparison' => $comparison,
                    ]);
                } catch (\Throwable $e) {
                    return view('filament.pages.partials.erp-comparison-modal', [
                        'error' => $e->getMessage(),
                    ]);
                }
            })
            ->form(function ($livewire): array {
                $teacher = static::resolveTeacher($livewire);
                if (! $teacher) {
                    return [];
                }

                $sectionsDef = TeacherErpComparisonService::getSectionsDefinition();
                $options = [];
                $defaultChecked = [];

                try {
                    $comparison = app(TeacherErpComparisonService::class)->compare($teacher);
                    $compSections = $comparison['sections'] ?? [];

                    foreach ($sectionsDef as $secKey => $def) {
                        $st = $compSections[$secKey]['status'] ?? 'match';
                        $badge = match ($st) {
                            'different' => ' (Differences Found)',
                            'new' => ' (New in ERP)',
                            'fms_only' => ' (FMS Only)',
                            default => ' (In Sync)',
                        };
                        $options[$secKey] = $def['label'] . $badge;

                        if ($st === 'different' || $st === 'new') {
                            $defaultChecked[] = $secKey;
                        }
                    }
                } catch (\Throwable) {
                    foreach ($sectionsDef as $secKey => $def) {
                        $options[$secKey] = $def['label'];
                    }
                }

                return [
                    Radio::make('sync_mode')
                        ->label('Import Mode')
                        ->options([
                            TeacherErpComparisonService::MODE_MERGE => 'Merge (Recommended: Preserves existing FMS profile data, only fills empty fields and adds new records from ERP)',
                            TeacherErpComparisonService::MODE_OVERRIDE => 'Override (Replaces current profile records with ERP records for the selected sections)',
                        ])
                        ->default(TeacherErpComparisonService::MODE_MERGE)
                        ->required(),

                    CheckboxList::make('selected_sections')
                        ->label('Select Sections to Import')
                        ->options($options)
                        ->default($defaultChecked)
                        ->columns(2)
                        ->helperText('Only checked sections will be updated. Unchecked sections will remain untouched.')
                        ->required(),
                ];
            })
            ->action(function (array $data, $livewire): void {
                $teacher = static::resolveTeacher($livewire);

                if (! $teacher) {
                    Notification::make()
                        ->danger()
                        ->title('Teacher record not found')
                        ->send();
                    return;
                }

                $selectedSections = $data['selected_sections'] ?? [];
                $mode = $data['sync_mode'] ?? TeacherErpComparisonService::MODE_MERGE;

                if (empty($selectedSections)) {
                    Notification::make()
                        ->warning()
                        ->title('No sections selected')
                        ->body('Please select at least one section to synchronize.')
                        ->send();
                    return;
                }

                // If user is admin, allow skipping approval
                $user = auth()->user();
                $skipApproval = $user && ! $user->isTeacher();

                try {
                    $result = app(TeacherErpComparisonService::class)->applySync(
                        $teacher,
                        $selectedSections,
                        $mode,
                        $skipApproval
                    );

                    $modeLabel = $mode === TeacherErpComparisonService::MODE_MERGE ? 'Merged' : 'Overridden';
                    Notification::make()
                        ->success()
                        ->title('ERP Synchronization Completed')
                        ->body("Successfully {$modeLabel} " . count($selectedSections) . ' section(s) with ERP records.')
                        ->send();

                    // Refresh form state completely by reloading the page so all repeaters/tabs mount fresh
                    try {
                        if (method_exists($livewire, 'fillForm') && is_callable([$livewire, 'fillForm'])) {
                            $livewire->fillForm();
                        }
                    } catch (\Throwable) {}

                    $redirectUrl = request()->header('Referer');
                    if (blank($redirectUrl)) {
                        if (method_exists($livewire, 'getResource')) {
                            $redirectUrl = $livewire->getResource()::getUrl('edit', ['record' => $teacher]);
                        } else {
                            $redirectUrl = request()->url();
                        }
                    }

                    $livewire->redirect($redirectUrl, navigate: false);
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Error synchronizing with ERP')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    public static function resolveTeacher($livewire): ?Teacher
    {
        if (method_exists($livewire, 'getRecord')) {
            $record = $livewire->getRecord();
            if ($record instanceof Teacher) {
                return $record;
            }
        }

        if (property_exists($livewire, 'record') && $livewire->record instanceof Teacher) {
            return $livewire->record;
        }

        return auth()->user()?->teacher;
    }
}
