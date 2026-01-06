<?php

namespace App\Filament\Resources\DegreeTypes\Pages;

use App\Filament\Resources\DegreeTypes\DegreeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDegreeType extends EditRecord
{
    protected static string $resource = DegreeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
