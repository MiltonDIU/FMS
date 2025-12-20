<?php

namespace App\Filament\Resources\AdministrativeRoles\Pages;

use App\Filament\Resources\AdministrativeRoles\AdministrativeRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAdministrativeRole extends EditRecord
{
    protected static string $resource = AdministrativeRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
