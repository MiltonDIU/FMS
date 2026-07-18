<?php

namespace App\Filament\Resources\AuthorTypes\Pages;

use App\Filament\Resources\AuthorTypes\AuthorTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuthorType extends CreateRecord
{
    protected static string $resource = AuthorTypeResource::class;
}
