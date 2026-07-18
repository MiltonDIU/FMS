<?php

namespace App\Filament\Resources\AuthorTypes\Pages;

use App\Filament\Resources\AuthorTypes\AuthorTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditAuthorType extends EditRecord
{
    protected static string $resource = AuthorTypeResource::class;
}
