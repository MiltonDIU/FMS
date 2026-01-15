<?php

namespace App\Filament\Resources\DepartmentTeachers\Pages;

use App\Filament\Resources\DepartmentTeachers\DepartmentTeacherResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDepartmentTeacher extends CreateRecord
{
    protected static string $resource = DepartmentTeacherResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set assigned_by to current user
        $data['assigned_by'] = auth()->id();

        // Auto-calculate sort_order if not set
        if (!isset($data['sort_order']) || $data['sort_order'] === 0) {
            $maxSort = \App\Models\DepartmentTeacher::where('department_id', $data['department_id'])
                ->max('sort_order');
            $data['sort_order'] = ($maxSort ?? 0) + 1;
        }

        return $data;
    }
}
