<?php

namespace App\Filament\Resources\DepartmentTeachers\Pages;

use App\Filament\Resources\DepartmentTeachers\DepartmentTeacherResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartmentTeacher extends EditRecord
{
    protected static string $resource = DepartmentTeacherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
