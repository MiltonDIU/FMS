<?php

namespace App\Filament\Resources\Religions\Pages;

use App\Filament\Resources\Religions\ReligionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListReligions extends ListRecords
{
    protected static string $resource = ReligionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}