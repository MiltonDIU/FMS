<?php

namespace App\Filament\Resources\ApprovalSettings\Pages;

use App\Filament\Resources\ApprovalSettings\ApprovalSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovalSettings extends ListRecords
{
    protected static string $resource = ApprovalSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
