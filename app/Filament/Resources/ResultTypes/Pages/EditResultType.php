<?php

namespace App\Filament\Resources\ResultTypes\Pages;

use App\Filament\Resources\ResultTypes\ResultTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResultType extends EditRecord
{
    protected static string $resource = ResultTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
