<?php

namespace App\Filament\Resources\Religions\Pages;

use App\Filament\Resources\Religions\ReligionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateReligion extends CreateRecord
{
    protected static string $resource = ReligionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['name']);
        return $data;
    }
}