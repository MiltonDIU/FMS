<?php

namespace App\Filament\Resources\DegreeTypes\Pages;

use App\Filament\Resources\DegreeTypes\DegreeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDegreeTypes extends ManageRecords
{
    protected static string $resource = DegreeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
