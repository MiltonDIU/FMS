<?php

namespace App\Filament\Resources\Teachers\Actions;

use App\Models\Teacher;
use App\Services\ProfileGapEvaluator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class RefreshProfileScoreAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'refresh_profile_score';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Refresh Score')
            ->tooltip('Recalculate your profile completion score based on current data')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(function ($livewire): bool {
                $teacher = static::resolveTeacher($livewire);
                if (! $teacher) {
                    return false;
                }
                return auth()->user()?->can('syncProfileScore', $teacher) ?? false;
            })
            ->action(function ($livewire): void {
                $teacher = static::resolveTeacher($livewire);

                if (! $teacher) {
                    Notification::make()
                        ->danger()
                        ->title('Teacher profile not found')
                        ->send();
                    return;
                }

                try {
                    $teacher->load([
                        'educations.degreeType.level',
                        'educations.educationalInstitution',
                        'publications',
                        'jobExperiences',
                        'trainingExperiences',
                        'awards',
                        'skills',
                        'teachingAreas',
                        'memberships',
                        'socialLinks',
                    ]);

                    $evaluator = new ProfileGapEvaluator();
                    $report = $evaluator->evaluate($teacher);
                    $score = (int) ($report['completion_percentage'] ?? 0);

                    $teacher->updateQuietly([
                        'profile_score' => $score,
                        'profile_score_synced_at' => Carbon::now(),
                    ]);

                    if (property_exists($livewire, 'gapReport')) {
                        $livewire->gapReport = $report;
                    }

                    Notification::make()
                        ->success()
                        ->title('Profile Score Refreshed')
                        ->body("Profile completeness score: {$score}% (Calculated at " . Carbon::now()->format('h:i A') . ')')
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Failed to refresh profile score')
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
