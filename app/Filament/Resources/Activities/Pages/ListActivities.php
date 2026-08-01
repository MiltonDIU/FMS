<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    /** No create action: the trail is written by the application, not by hand. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
