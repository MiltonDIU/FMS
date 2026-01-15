<?php

namespace App\Filament\Resources\AdministrativeRoleUsers\Pages;

use App\Filament\Resources\AdministrativeRoleUsers\AdministrativeRoleUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdministrativeRoleUsers extends ListRecords
{
    protected static string $resource = AdministrativeRoleUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
