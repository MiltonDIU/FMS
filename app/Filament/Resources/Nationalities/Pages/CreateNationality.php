<?php

namespace App\Filament\Resources\Nationalities\Pages;

use App\Filament\Resources\Nationalities\NationalityResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateNationality extends CreateRecord
{
    protected static string $resource = NationalityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['name']);
        return $data;
    }
}