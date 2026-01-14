<?php

namespace App\Filament\Resources\PublicationIncentives\Pages;

use App\Filament\Resources\PublicationIncentives\PublicationIncentiveResource;
use Filament\Resources\Pages\ListRecords;

class ListPublicationIncentives extends ListRecords
{
    protected static string $resource = PublicationIncentiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array

    {
        return [
            \App\Filament\Widgets\PublicationIncentiveStatsOverview::class,
        ];
    }
}
