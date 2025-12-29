<?php

namespace App\Filament\Resources\TeacherVersions\Pages;

use App\Filament\Resources\TeacherVersions\TeacherVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacherVersion extends EditRecord
{
    protected static string $resource = TeacherVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('approve')
                ->label('Approve Changes')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    app(\App\Services\TeacherVersionService::class)->approveVersion($this->record);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Version approved successfully')
                        ->success()
                        ->send();
                        
                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->visible(fn () => $this->record->status === 'pending'),

            \Filament\Actions\Action::make('reject')
                ->label('Reject Changes')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Textarea::make('remarks')
                        ->label('Rejection Remarks')
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(\App\Services\TeacherVersionService::class)->rejectVersion($this->record, $data['remarks']);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Version rejected')
                        ->danger()
                        ->send();
                        
                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->visible(fn () => $this->record->status === 'pending'),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
