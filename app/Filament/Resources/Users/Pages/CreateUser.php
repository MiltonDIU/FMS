<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * An account an admin creates is verified from the moment it exists, and
     * that is the only time this form sets it — EditUser never touches it.
     * Assigned directly because email_verified_at is deliberately not
     * fillable, so no form field can write it.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new ($this->getModel())($data);
        $record->email_verified_at = now();
        $record->save();

        return $record;
    }
}
