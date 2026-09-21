<?php

namespace App\Filament\Resources\NamePrefixes\Pages;

use App\Filament\Resources\NamePrefixes\NamePrefixResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNamePrefixes extends ListRecords
{
    protected static string $resource = NamePrefixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
