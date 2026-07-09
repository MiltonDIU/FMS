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
                    $cache = app(\App\Services\DuplicateFinderService::class)->getSuggestionsWithCache('major');
                    return view('filament.lookup.ai-merge-suggestions', [
                        'cache' => $cache,
                        'type' => 'major',
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalWidth('4xl'),
        ];
    }

    public function refreshAiScan(string $type): void
    {
        app(\App\Services\DuplicateFinderService::class)->getSuggestionsWithCache($type, forceRefresh: true);
        
        Notification::make()
            ->title('AI duplicate scan refreshed successfully')
            ->success()
            ->send();
            
        $this->mountAction('aiSuggestMerges');
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

        // Update the cached file dynamically
        app(\App\Services\DuplicateFinderService::class)->removeGroupFromCache($type, $targetId, $sourceIds);

        Notification::make()
            ->title('Merged successfully')
            ->success()
            ->send();

        // Re-mount the action modal to display remaining duplicate groups
        $this->mountAction('aiSuggestMerges');
    }
}
