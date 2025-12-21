<?php

namespace App\Filament\Resources\BloodGroups\Pages;

use App\Filament\Resources\BloodGroups\BloodGroupResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditBloodGroup extends EditRecord
{
    protected static string $resource = BloodGroupResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        return $data;
    }
}