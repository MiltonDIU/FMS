<?php

namespace App\Filament\Resources\AuthorTypes\Pages;

use App\Filament\Resources\AuthorTypes\AuthorTypeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListAuthorTypes extends ListRecords
{
    protected static string $resource = AuthorTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
