<?php

namespace App\Filament\Resources\AdministrativeRoles\Pages;

use App\Filament\Resources\AdministrativeRoles\AdministrativeRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdministrativeRoles extends ListRecords
{
    protected static string $resource = AdministrativeRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
