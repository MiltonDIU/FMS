<?php

namespace App\Filament\Resources\EducationalInstitutions\Pages;

use App\Filament\Resources\EducationalInstitutions\EducationalInstitutionResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;
use App\Models\EducationalInstitution;
use Filament\Notifications\Notification;

class ManageEducationalInstitutions extends ManageRecords
{
    protected static string $resource = EducationalInstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('aiSuggestMerges')
                ->label('Scan Duplicates (AI)')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->modalHeading('AI-Powered Duplicate Suggestions')
                ->modalContent(function () {
                    $suggestions = app(\App\Services\DuplicateFinderService::class)->findDuplicates('institution');
                    return view('filament.lookup.ai-merge-suggestions', [
                        'suggestions' => $suggestions,
                        'type' => 'institution',
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalWidth('4xl'),
        ];
    }

    public function mergeGroup($targetId, array $allIds, string $type): void
    {
        $targetId = (int) $targetId;
        $allIds = array_map('intval', $allIds);
        $sourceIds = array_values(array_filter($allIds, fn($id) => $id !== $targetId));

        if (empty($sourceIds)) {
            return;
        }

        DB::transaction(function () use ($targetId, $sourceIds) {
            $targetRecord = EducationalInstitution::findOrFail($targetId);
            
            // 1. Update related educations
            DB::table('educations')
                ->whereIn('educational_institution_id', $sourceIds)
                ->update([
                    'educational_institution_id' => $targetId,
                    'institution' => $targetRecord->name,
                ]);
            
            // 2. Delete source records
            EducationalInstitution::whereIn('id', $sourceIds)->delete();
        });

        Notification::make()
            ->title('Merged successfully')
            ->success()
            ->send();

        // Close the modal
        $this->unmountAction();
    }
}
