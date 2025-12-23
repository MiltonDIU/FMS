<?php

namespace App\Filament\Resources\PublicationQuartiles\Pages;

use App\Filament\Resources\PublicationQuartiles\PublicationQuartileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPublicationQuartile extends EditRecord
{
    protected static string $resource = PublicationQuartileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
