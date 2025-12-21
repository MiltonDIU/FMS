<?php

namespace App\Filament\Resources\Genders\Pages;

use App\Filament\Resources\Genders\GenderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateGender extends CreateRecord
{
    protected static string $resource = GenderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['name']);
        return $data;
    }
}