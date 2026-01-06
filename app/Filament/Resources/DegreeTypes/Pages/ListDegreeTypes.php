<?php

namespace App\Filament\Resources\DegreeTypes\Pages;

use App\Filament\Resources\DegreeTypes\DegreeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDegreeTypes extends ListRecords
{
    protected static string $resource = DegreeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
