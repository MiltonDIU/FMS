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
            Action::make('send_all_verification_emails')
                ->label('Send Email to All Teachers')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send Verification Emails to ALL Teachers')
                ->modalDescription('This will dispatch verification emails to all teachers currently in the system.')
                ->action(function () {
                    $teachers = Teacher::all();
                    $count = 0;

                    foreach ($teachers as $teacher) {
                        SendTeacherVerificationEmailJob::dispatch($teacher);
                        $count++;
                    }

                    Notification::make()
                        ->title("Verification emails queued for {$count} teachers!")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
