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

    protected function getHeaderWidgets(): array
    {
        return [
           TeacherProfileStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TeacherProfileCompletionWidget::class,
            TeacherProfessionalInfoWidget::class,
            TeacherPublicationsStatsWidget::class,
            TeacherResearchStatsWidget::class,
            TeacherPublicationTrendWidget::class,
            TeacherQuickActionsWidget::class,
        ];
    }
}
