<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ViewRecord;

class ViewActivity extends ViewRecord
{
    protected static string $resource = ActivityResource::class;

    /** No edit or delete: an audit entry is not editable by anyone. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
