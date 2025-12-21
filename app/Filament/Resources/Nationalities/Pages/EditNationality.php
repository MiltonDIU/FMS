<?php

namespace App\Filament\Resources\Nationalities\Pages;

use App\Filament\Resources\Nationalities\NationalityResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditNationality extends EditRecord
{
    protected static string $resource = NationalityResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return $data;
    }
}