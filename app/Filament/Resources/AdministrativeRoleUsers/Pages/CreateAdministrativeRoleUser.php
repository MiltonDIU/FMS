<?php

namespace App\Filament\Resources\AdministrativeRoleUsers\Pages;

use App\Filament\Resources\AdministrativeRoleUsers\AdministrativeRoleUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdministrativeRoleUser extends CreateRecord
{
    protected static string $resource = AdministrativeRoleUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set assigned_by to current user
        $data['assigned_by'] = auth()->id();

        return $data;
    }
}
