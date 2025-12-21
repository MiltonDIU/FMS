<?php

namespace App\Filament\Resources\Religions\Pages;

use App\Filament\Resources\Religions\ReligionResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditReligion extends EditRecord
{
    protected static string $resource = ReligionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return $data;
    }
}