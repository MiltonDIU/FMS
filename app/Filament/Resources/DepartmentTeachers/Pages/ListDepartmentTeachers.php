<?php

namespace App\Filament\Resources\DepartmentTeachers\Pages;

use App\Filament\Resources\DepartmentTeachers\DepartmentTeacherResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepartmentTeachers extends ListRecords
{
    protected static string $resource = DepartmentTeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
