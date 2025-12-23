<?php

namespace App\Filament\Resources\GrantTypes\Pages;

use App\Filament\Resources\GrantTypes\GrantTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrantTypes extends ListRecords
{
    protected static string $resource = GrantTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
