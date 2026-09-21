<?php

namespace App\Filament\Resources\AcademicSuffixes\Pages;

use App\Filament\Resources\AcademicSuffixes\AcademicSuffixResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAcademicSuffixes extends ListRecords
{
    protected static string $resource = AcademicSuffixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
