<?php

namespace App\Filament\Resources\BloodGroups\Pages;

use App\Filament\Resources\BloodGroups\BloodGroupResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListBloodGroups extends ListRecords
{
    protected static string $resource = BloodGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}