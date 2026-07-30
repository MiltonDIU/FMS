<?php

namespace App\Filament\Resources\Teachers\Actions;

use App\Models\IntegrationMapping;
use App\Services\ErpImportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class ErpImportAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'erp_import';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Import from ERP')
            ->icon('heroicon-o-cloud-arrow-down')
            ->color('info')
            ->modalHeading('Import Teacher from ERP')
            ->modalDescription('Search the ERP system and import teacher data directly into the database.')
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Close')
            ->modalCancelAction(false)
            ->form(function () {
                return []; // Form is empty — UI is handled in the custom modal view
            })
            ->modalContent(fn ($livewire) => view(
                'filament.resources.teachers.actions.erp-import-modal',
                ['mappings' => IntegrationMapping::active()->get()]
            ))
            ->action(fn () => null); // No server action needed — import is done via Livewire dispatch
    }
}
