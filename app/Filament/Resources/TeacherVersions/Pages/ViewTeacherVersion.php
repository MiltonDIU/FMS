<?php

namespace App\Filament\Resources\TeacherVersions\Pages;

use App\Filament\Resources\TeacherVersions\TeacherVersionResource;
use App\Filament\Resources\TeacherVersions\Tables\TeacherVersionsTable;
use App\Services\TeacherVersionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * A version is a record of what happened to a profile, so it is read, not
 * edited. The generated edit page let anyone holding Update:TeacherVersion
 * rewrite that record — set a status to approved without the data ever being
 * applied, change who submitted it, or retarget it at another teacher — and
 * showed the submitter as a bare user id and the data as "[object Object]".
 *
 * Decisions still happen here, through the service, for whoever is routed to
 * decide a pending section.
 */
class ViewTeacherVersion extends ViewRecord
{
    protected static string $resource = TeacherVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve Changes')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Approves every pending section you are routed to decide. The data is applied to the profile at once.')
                ->visible(fn (): bool => ! empty($this->record->pending_sections) && TeacherVersionsTable::canDecideAny($this->record))
                ->action(function (): void {
                    app(TeacherVersionService::class)->approveVersion($this->record);

                    Notification::make()->title('Changes approved')->success()->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Action::make('reject')
                ->label('Reject Changes')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->schema([
                    Textarea::make('remarks')
                        ->label('Rejection Remarks')
                        ->required(),
                ])
                ->visible(fn (): bool => ! empty($this->record->pending_sections) && TeacherVersionsTable::canDecideAny($this->record))
                ->action(function (array $data): void {
                    app(TeacherVersionService::class)->rejectVersion($this->record, $data['remarks']);

                    Notification::make()->title('Changes rejected')->danger()->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
