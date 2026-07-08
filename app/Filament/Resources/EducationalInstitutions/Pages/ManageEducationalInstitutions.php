<?php

namespace App\Filament\Resources\EducationalInstitutions\Pages;

use App\Filament\Resources\EducationalInstitutions\EducationalInstitutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEducationalInstitutions extends ManageRecords
{
    protected static string $resource = EducationalInstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
