<?php

namespace App\Filament\Resources\AcademicSuffixes\Pages;

use App\Filament\Resources\AcademicSuffixes\AcademicSuffixResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAcademicSuffix extends EditRecord
{
    protected static string $resource = AcademicSuffixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
