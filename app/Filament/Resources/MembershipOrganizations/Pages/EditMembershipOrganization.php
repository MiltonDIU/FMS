<?php

namespace App\Filament\Resources\MembershipOrganizations\Pages;

use App\Filament\Resources\MembershipOrganizations\MembershipOrganizationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMembershipOrganization extends EditRecord
{
    protected static string $resource = MembershipOrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
