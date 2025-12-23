<?php

namespace App\Filament\Resources\GrantTypes\Pages;

use App\Filament\Resources\GrantTypes\GrantTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrantType extends EditRecord
{
    protected static string $resource = GrantTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
