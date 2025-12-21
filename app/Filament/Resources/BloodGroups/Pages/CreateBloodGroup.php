<?php

namespace App\Filament\Resources\BloodGroups\Pages;

use App\Filament\Resources\BloodGroups\BloodGroupResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateBloodGroup extends CreateRecord
{
    protected static string $resource = BloodGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['name']);
        return $data;
    }
}