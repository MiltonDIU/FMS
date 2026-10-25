<?php

namespace App\Filament\Resources\MyFeedback\Pages;

use App\Filament\Resources\MyFeedback\MyFeedbackResource;
use App\Models\Feedback;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateMyFeedback extends CreateRecord
{
    protected static string $resource = MyFeedbackResource::class;

    protected static ?string $title = 'Send feedback';

    protected static bool $canCreateAnother = false;

    /** Through Feedback::submit(), so the team is told. */
    protected function handleRecordCreation(array $data): Model
    {
        return Feedback::submit(Auth::user(), $data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Thank you — your feedback has been sent';
    }

    protected function getRedirectUrl(): string
    {
        return MyFeedbackResource::getUrl('view', ['record' => $this->record]);
    }
}
