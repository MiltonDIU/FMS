<?php

namespace App\Filament\Resources\EmailBatches\Pages;

use App\Filament\Resources\EmailBatches\EmailBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailBatches extends ListRecords
{
    protected static string $resource = EmailBatchResource::class;

    /** Nothing is created here: a batch exists because an email was sent. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
