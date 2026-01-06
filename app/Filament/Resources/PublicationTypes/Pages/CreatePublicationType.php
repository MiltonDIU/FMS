<?php

namespace App\Filament\Resources\PublicationTypes\Pages;

use App\Filament\Resources\PublicationTypes\PublicationTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublicationType extends CreateRecord
{
    protected static string $resource = PublicationTypeResource::class;
}
