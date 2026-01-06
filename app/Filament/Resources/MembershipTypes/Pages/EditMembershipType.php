<?php

namespace App\Filament\Resources\MembershipTypes\Pages;

use App\Filament\Resources\MembershipTypes\MembershipTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMembershipType extends EditRecord
{
    protected static string $resource = MembershipTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
