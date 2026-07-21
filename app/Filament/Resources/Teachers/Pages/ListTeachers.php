<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Teachers\Widgets\TeacherVerificationStatsWidget;
use App\Jobs\SendTeacherVerificationEmailJob;
use App\Models\Teacher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TeacherVerificationStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_batch_profile_scores')
                ->label('Batch Calculate Profile Scores')
                ->icon('heroicon-o-calculator')
                ->color('info')
                ->modalHeading('Batch Recalculate Teacher Profile Scores')
                ->modalDescription('Calculate and save updated profile completion scores for selected teachers based on current criteria.')
                ->form([
                    \Filament\Forms\Components\Select::make('employment_status_id')
                        ->label('Employment Status')
                        ->placeholder('All Employment Statuses (Full-time, Part-time, Suspended, etc.)')
                        ->options(fn () => \App\Models\EmploymentStatus::query()->pluck('name', 'id')->toArray())
                        ->searchable()
                        ->nullable(),

                    \Filament\Forms\Components\Toggle::make('force_resync')
                        ->label('Force Re-sync All')
                        ->helperText('If enabled, recalculates scores for all matching teachers even if synced recently.')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $query = Teacher::query()->where('is_archived', false);

                    // Employment Status filter
                    if (!empty($data['employment_status_id'])) {
                        $query->where('employment_status_id', $data['employment_status_id']);
                    }

                    // Force resync check
                    if (empty($data['force_resync'])) {
                        $query->where(function ($q) {
                            $q->whereNull('profile_score_synced_at')
                              ->orWhere('profile_score_synced_at', '<', \Illuminate\Support\Carbon::now()->subHours(6));
                        });
                    }

                    $total = $query->count();

                    if ($total === 0) {
                        Notification::make()
                            ->warning()
                            ->title('No matching teachers found')
                            ->body('No teachers matched the selected criteria for scoring.')
                            ->send();
                        return;
                    }

                    @set_time_limit(300);

                    $evaluator = new \App\Services\ProfileGapEvaluator();
                    $processed = 0;

                    $query->with([
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
                    ])->chunkById(100, function ($teachers) use ($evaluator, &$processed) {
                        $updates = [];
                        $now = \Illuminate\Support\Carbon::now()->toDateTimeString();

                        foreach ($teachers as $teacher) {
                            $report = $evaluator->evaluate($teacher);
                            $score  = $report['completion_percentage'];

                            $updates[] = [
                                'id'                      => $teacher->id,
                                'profile_score'           => $score,
                                'profile_score_synced_at' => $now,
                            ];
                            $processed++;
                        }

                        if (!empty($updates)) {
                            $ids = array_column($updates, 'id');
                            $scoreCase  = 'CASE id ';
                            $syncedCase = 'CASE id ';

                            foreach ($updates as $u) {
                                $scoreCase  .= "WHEN {$u['id']} THEN {$u['profile_score']} ";
                                $syncedCase .= "WHEN {$u['id']} THEN '{$u['profile_score_synced_at']}' ";
                            }

                            $scoreCase  .= 'END';
                            $syncedCase .= 'END';
                            $idList = implode(',', $ids);

                            \Illuminate\Support\Facades\DB::statement("
                                UPDATE teachers
                                SET profile_score = {$scoreCase},
                                    profile_score_synced_at = {$syncedCase}
                                WHERE id IN ({$idList})
                            ");
                        }
                    });

                    Notification::make()
                        ->success()
                        ->title('Profile Scoring Completed!')
                        ->body("Successfully recalculated and updated profile scores for {$processed} teachers.")
                        ->send();
                }),
            Action::make('send_targeted_email')
                ->label('Send Email to Teachers')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->modalHeading('Send Targeted Email to Teachers')
                ->modalDescription('Filter teachers by employment status, select a saved email template or write custom content, and send.')
                ->form([
                    \Filament\Forms\Components\Select::make('employment_status_ids')
                        ->label('Target Employment Statuses (Multi-Select)')
                        ->placeholder('All Employment Statuses (Full-time, Part-time, Suspended, etc.)')
                        ->options(fn () => \App\Models\EmploymentStatus::query()->pluck('name', 'id')->toArray())
                        ->multiple()
                        ->searchable()
                        ->columnSpanFull()
                        ->helperText('Leave empty to send email to all active teachers.'),

                    \Filament\Forms\Components\Select::make('template_id')
                        ->label('Select Saved Email Template')
                        ->placeholder('Choose a template to load default subject & body...')
                        ->options(fn () => \App\Models\EmailTemplate::query()->where('is_active', true)->pluck('name', 'id')->toArray())
                        ->searchable()
                        ->live()
                        ->columnSpanFull()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if ($state) {
                                $template = \App\Models\EmailTemplate::find($state);
                                if ($template) {
                                    $set('subject', $template->subject);
                                    $set('body', $template->body);
                                }
                            }
                        }),

                    \Filament\Forms\Components\TextInput::make('subject')
                        ->label('Email Subject Line')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->placeholder('e.g. Action Required: Please Review & Confirm Your Profile Data'),

                    \Filament\Forms\Components\Textarea::make('body')
                        ->label('Email Body / Message')
                        ->required()
                        ->rows(7)
                        ->columnSpanFull()
                        ->helperText('Available placeholders: {teacher_name}, {employee_id}, {department}, {designation}, {profile_score}, {verification_link}'),
                ])
                ->action(function (array $data) {
                    $query = Teacher::query()->where('is_archived', false);

                    if (!empty($data['employment_status_ids']) && is_array($data['employment_status_ids'])) {
                        $query->whereIn('employment_status_id', $data['employment_status_ids']);
                    }

                    $teachers = $query->get();
                    $count    = $teachers->count();

                    if ($count === 0) {
                        Notification::make()
                            ->warning()
                            ->title('No matching teachers found')
                            ->body('No teachers matched the selected employment statuses.')
                            ->send();
                        return;
                    }

                    $subject = $data['subject'];
                    $body    = $data['body'];

                    foreach ($teachers as $teacher) {
                        \App\Jobs\SendCustomTemplatedEmailJob::dispatch($teacher, $subject, $body);
                    }

                    Notification::make()
                        ->title("Targeted email job queued for {$count} teachers!")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
