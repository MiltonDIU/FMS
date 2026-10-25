<?php

namespace App\Filament\Resources\MyFeedback\Pages;

use App\Filament\Resources\MyFeedback\MyFeedbackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMyFeedback extends ListRecords
{
    protected static string $resource = MyFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Send feedback')
                ->icon('heroicon-o-plus'),
        ];
    }
}
