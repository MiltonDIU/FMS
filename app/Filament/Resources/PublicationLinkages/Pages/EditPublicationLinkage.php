<?php

namespace App\Filament\Resources\PublicationLinkages\Pages;

use App\Filament\Resources\PublicationLinkages\PublicationLinkageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPublicationLinkage extends EditRecord
{
    protected static string $resource = PublicationLinkageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
