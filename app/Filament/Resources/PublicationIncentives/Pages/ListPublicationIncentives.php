<?php

namespace App\Filament\Resources\PublicationIncentives\Pages;

use App\Filament\Resources\PublicationIncentives\PublicationIncentiveResource;
use App\Jobs\ExportIncentivesJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPublicationIncentives extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PublicationIncentiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_incentives_background')
                ->label('Export Incentives')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                // Whatever the table is currently showing — the filters and the
                // search box are handed to the job, so what downloads is what is
                // on screen rather than the whole table.
                ->visible(fn (): bool => auth()->user()?->can('ViewAny:PublicationIncentive') ?? false)
                ->action(fn () => $this->startExport('incentive')),

            Action::make('export_incentive_authors_background')
                ->label('Export by Author')
                ->icon(Heroicon::OutlinedUsers)
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('ViewAny:PublicationIncentive') ?? false)
                ->action(fn () => $this->startExport('author')),

            CreateAction::make(),
        ];
    }

    protected function startExport(string $mode): void
    {
        ExportIncentivesJob::dispatch(
            auth()->user(),
            $this->tableFilters ?? [],
            $this->tableSearch,
            $mode,
        );

        Notification::make()
            ->title('Export Started')
            ->body('We will notify you when the file is ready.')
            ->success()
            ->send();
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\PublicationIncentiveStatsOverview::class,
        ];
    }
}
