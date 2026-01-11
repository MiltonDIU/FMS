<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListPublications extends ListRecords
{
    protected static string $resource = PublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_all_background')
                ->label('Export All (Background)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->action(function () {
                    $user = auth()->user();
                    
                    // Retrieve filters from the table component
                    // Since we are in the Page class, $this refers to ListPublications page which manages the table
                    // Filament pages using ListRecords have tableFilters and tableSearch available
                    $filters = $this->tableFilters;
                    $search = $this->tableSearch;

                    \App\Jobs\ExportPublicationsJob::dispatch($user, $filters, $search);

                    \Filament\Notifications\Notification::make()
                        ->title('Export Started')
                        ->body('We will notify you when the file is ready.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}





