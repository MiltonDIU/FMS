<?php

namespace App\Filament\Resources\DegreeLevels\Pages;

use App\Filament\Resources\DegreeLevels\DegreeLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDegreeLevel extends EditRecord
{
    protected static string $resource = DegreeLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
