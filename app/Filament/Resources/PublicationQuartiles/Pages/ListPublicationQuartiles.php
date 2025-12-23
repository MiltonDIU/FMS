<?php

namespace App\Filament\Resources\PublicationQuartiles\Pages;

use App\Filament\Resources\PublicationQuartiles\PublicationQuartileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublicationQuartiles extends ListRecords
{
    protected static string $resource = PublicationQuartileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
