<?php

namespace App\Filament\Resources\NotificationRoutings\Pages;

use App\Filament\Resources\NotificationRoutings\NotificationRoutingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNotificationRouting extends EditRecord
{
    protected static string $resource = NotificationRoutingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
