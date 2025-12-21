<?php

namespace App\Filament\Resources\Genders\Pages;

use App\Filament\Resources\Genders\GenderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListGenders extends ListRecords
{
    protected static string $resource = GenderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}