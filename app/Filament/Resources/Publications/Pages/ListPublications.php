<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
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
    // Passes the table's filter/search/sort state down to the header widgets,
    // so PublicationSourceStatsWidget can mirror the active filters.
    use ExposesTableToWidgets;

    protected static string $resource = PublicationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\PublicationSourceStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_all_background')
                ->label('Export Publications')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                // Writes the whole filtered dataset to a downloadable file.
                ->visible(fn (): bool => auth()->user()?->can('ViewAny:Publication') ?? false)
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
                ->visible(fn (): bool => (auth()->user()?->can('ViewAny:Publication') ?? false)
                    && (auth()->user()?->can('ViewAny:Author') ?? false))
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





