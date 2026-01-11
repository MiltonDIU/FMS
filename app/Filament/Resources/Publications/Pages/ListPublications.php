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
                ->label('Export Publications')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->action(function () {
                    $user = auth()->user();
                    $filters = $this->tableFilters;
                    $search = $this->tableSearch;

                    \App\Jobs\ExportPublicationsJob::dispatch($user, $filters, $search, 'publication');

                    \Filament\Notifications\Notification::make()
                        ->title('Export Started')
                        ->body('We will notify you when the file is ready.')
                        ->success()
                        ->send();
                }),

            Action::make('export_authors_background')
                ->label('Export by Author')
                ->icon(Heroicon::OutlinedUsers)
                ->color('warning')
                ->action(function () {
                    $user = auth()->user();
                    $filters = $this->tableFilters;
                    $search = $this->tableSearch;

                    \App\Jobs\ExportPublicationsJob::dispatch($user, $filters, $search, 'author');

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





