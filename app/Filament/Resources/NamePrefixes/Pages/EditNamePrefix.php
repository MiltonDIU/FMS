<?php

namespace App\Filament\Resources\NamePrefixes\Pages;

use App\Filament\Resources\NamePrefixes\NamePrefixResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNamePrefix extends EditRecord
{
    protected static string $resource = NamePrefixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
