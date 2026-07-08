<?php

namespace App\Filament\Resources\Majors\Pages;

use App\Filament\Resources\Majors\MajorResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;
use App\Models\Major;
use Filament\Notifications\Notification;

class ManageMajors extends ManageRecords
{
    protected static string $resource = MajorResource::class;

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
                    $suggestions = app(\App\Services\DuplicateFinderService::class)->findDuplicates('major');
                    return view('filament.lookup.ai-merge-suggestions', [
                        'suggestions' => $suggestions,
                        'type' => 'major',
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
            $targetRecord = Major::findOrFail($targetId);
            
            // 1. Update related educations
            DB::table('educations')
                ->whereIn('major_id', $sourceIds)
                ->update([
                    'major_id' => $targetId,
                    'major' => $targetRecord->name,
                ]);
            
            // 2. Delete source records
            Major::whereIn('id', $sourceIds)->delete();
        });

        Notification::make()
            ->title('Merged successfully')
            ->success()
            ->send();

        // Close the modal
        $this->unmountAction();
    }
}
