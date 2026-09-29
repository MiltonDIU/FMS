<?php

namespace App\Filament\Resources\TeacherVersions\Pages;

use App\Filament\Resources\TeacherVersions\TeacherVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListTeacherVersions extends ListRecords
{
    protected static string $resource = TeacherVersionResource::class;

    /**
     * No "New" button: a version is created by saving a profile, never by
     * hand, so the history holds only what actually happened.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
