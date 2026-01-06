<?php

namespace App\Filament\Resources\EmploymentStatuses\Pages;

use App\Filament\Resources\EmploymentStatuses\EmploymentStatusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmploymentStatus extends CreateRecord
{
    protected static string $resource = EmploymentStatusResource::class;
}
