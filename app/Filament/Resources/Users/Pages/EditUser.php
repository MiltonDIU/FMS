<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\AccessAudit;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** The roles before the save, so the change to them can be recorded. */
    protected array $rolesBefore = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /*
     * The roles field saves through its relationship, a sync on the pivot that
     * nothing else sees. afterSave() runs once it has.
     */
    protected function beforeSave(): void
    {
        $this->rolesBefore = AccessAudit::names($this->record, 'roles');
    }

    protected function afterSave(): void
    {
        AccessAudit::record($this->record, 'roles', $this->rolesBefore);
    }
}
