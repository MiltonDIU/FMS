<?php

namespace App\Filament\Resources\Teachers\Pages;

use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Widgets\TeacherProfessionalInfoWidget;
use App\Filament\Widgets\TeacherProfileCompletionWidget;
use App\Filament\Widgets\TeacherProfileStatsWidget;
use App\Filament\Widgets\TeacherPublicationTrendWidget;
use App\Filament\Widgets\TeacherPublicationsStatsWidget;
use App\Filament\Widgets\TeacherQuickActionsWidget;
use App\Filament\Widgets\TeacherResearchStatsWidget;
use Filament\Resources\Pages\ViewRecord;

class ViewTeacher extends ViewRecord
{
    protected static string $resource = TeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
        ];
    }

    // Widgets removed as per request to have a cleaner view page

}
