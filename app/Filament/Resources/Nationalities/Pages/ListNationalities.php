<?php

namespace App\Filament\Resources\Nationalities\Pages;

use App\Filament\Resources\Nationalities\NationalityResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListNationalities extends ListRecords
{
    protected static string $resource = NationalityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}