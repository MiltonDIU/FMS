<?php

namespace App\Filament\Resources\AdministrativeRoleUsers\Pages;

use App\Filament\Resources\AdministrativeRoleUsers\AdministrativeRoleUserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAdministrativeRoleUser extends EditRecord
{
    protected static string $resource = AdministrativeRoleUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
