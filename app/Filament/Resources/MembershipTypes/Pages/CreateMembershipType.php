<?php

namespace App\Filament\Resources\MembershipTypes\Pages;

use App\Filament\Resources\MembershipTypes\MembershipTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembershipType extends CreateRecord
{
    protected static string $resource = MembershipTypeResource::class;
}
