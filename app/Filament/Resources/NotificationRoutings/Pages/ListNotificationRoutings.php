<?php

namespace App\Filament\Resources\NotificationRoutings\Pages;

use App\Filament\Resources\NotificationRoutings\NotificationRoutingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationRoutings extends ListRecords
{
    protected static string $resource = NotificationRoutingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
