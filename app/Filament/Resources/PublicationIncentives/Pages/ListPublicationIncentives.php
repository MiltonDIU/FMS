<?php

namespace App\Filament\Resources\PublicationIncentives\Pages;

use App\Filament\Resources\PublicationIncentives\PublicationIncentiveResource;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListPublicationIncentives extends ListRecords
{
    use ExposesTableToWidgets;

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
