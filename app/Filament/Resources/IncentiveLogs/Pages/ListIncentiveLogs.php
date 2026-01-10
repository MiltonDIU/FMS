<?php

namespace App\Filament\Resources\IncentiveLogs\Pages;

use App\Filament\Resources\IncentiveLogs\IncentiveLogResource;
use Filament\Resources\Pages\ListRecords;

class ListIncentiveLogs extends ListRecords
{
    protected static string $resource = IncentiveLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
