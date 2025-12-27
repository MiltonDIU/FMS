<?php

namespace App\Filament\Resources\MembershipOrganizations\Pages;

use App\Filament\Resources\MembershipOrganizations\MembershipOrganizationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMembershipOrganizations extends ListRecords
{
    protected static string $resource = MembershipOrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
