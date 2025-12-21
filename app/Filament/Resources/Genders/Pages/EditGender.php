<?php

namespace App\Filament\Resources\Genders\Pages;

use App\Filament\Resources\Genders\GenderResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditGender extends EditRecord
{
    protected static string $resource = GenderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return $data;
    }
}