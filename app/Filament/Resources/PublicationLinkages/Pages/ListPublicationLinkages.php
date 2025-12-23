<?php

namespace App\Filament\Resources\PublicationLinkages\Pages;

use App\Filament\Resources\PublicationLinkages\PublicationLinkageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublicationLinkages extends ListRecords
{
    protected static string $resource = PublicationLinkageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
