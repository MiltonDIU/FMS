<?php

namespace App\Filament\Resources\TeacherVersions\Pages;

use App\Filament\Resources\TeacherVersions\TeacherVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeacherVersions extends ListRecords
{
    protected static string $resource = TeacherVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
