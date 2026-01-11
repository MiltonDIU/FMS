<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\Faculty;
use Filament\Forms\Components\Select;
// use Filament\Forms\Form; // Removed
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema; // Added
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    // use HasFiltersForm;
    // Filters removed as per user request to reset.

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\QueueStatusWidget::class,
        ];
    }
}
