<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use Filament\Notifications\Notification;

class ManageOrganizations extends ManageRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('aiSuggestMerges')
                ->label('Scan Duplicates (AI)')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                // Opens the merge UI, whose buttons call mergeGroup(), and
                // a refresh hits the paid AI service.
                ->visible(fn (): bool => auth()->user()?->can('Delete:Organization') ?? false)
                ->modalHeading('AI-Powered Duplicate Suggestions')
                ->modalContent(function () {
                    $cache = app(\App\Services\DuplicateFinderService::class)->getSuggestionsWithCache('organization');
                    return view('filament.lookup.ai-merge-suggestions', [
                        'cache' => $cache,
                        'type' => 'organization',
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalWidth('4xl'),
        ];
    }

    public function refreshAiScan(string $type): void
    {
        // Callable from the browser like mergeGroup below, and it spends money:
        // a forced refresh calls the AI duplicate service.
        abort_unless(auth()->user()?->can('Update:Organization'), 403);

        app(\App\Services\DuplicateFinderService::class)->getSuggestionsWithCache($type, forceRefresh: true);
        
        Notification::make()
            ->title('AI duplicate scan refreshed successfully')
            ->success()
            ->send();
            
        $this->mountAction('aiSuggestMerges');
    }

    public function mergeGroup($targetId, array $allIds, string $type): void
    {
        // Public methods on a Livewire page are callable straight from the
        // browser, outside the action pipeline where visible() and authorize()
        // would run. This one rewrites foreign keys and then deletes rows, so it
        // needs the delete permission the page itself never asked for — reaching
        // this page only requires ViewAny.
        abort_unless(auth()->user()?->can('Delete:Organization'), 403);

        $targetId = (int) $targetId;
        $allIds = array_map('intval', $allIds);
        $sourceIds = array_values(array_filter($allIds, fn($id) => $id !== $targetId));

        if (empty($sourceIds)) {
            return;
        }

        DB::transaction(function () use ($targetId, $sourceIds) {
            $targetRecord = Organization::findOrFail($targetId);
            
            // 1. Update related job experiences
            DB::table('job_experiences')
                ->whereIn('organization_id', $sourceIds)
                ->update([
                    'organization_id' => $targetId,
                    'organization' => $targetRecord->name,
                ]);
            
            // 2. Delete source records
            Organization::whereIn('id', $sourceIds)->delete();
        });

        // Update cached file dynamically
        app(\App\Services\DuplicateFinderService::class)->removeGroupFromCache($type, $targetId, $sourceIds);

        Notification::make()
            ->title('Merged successfully')
            ->success()
            ->send();

        // Re-mount action modal to display remaining duplicate groups
        $this->mountAction('aiSuggestMerges');
    }
}
