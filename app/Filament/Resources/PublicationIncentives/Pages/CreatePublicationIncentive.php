<?php

namespace App\Filament\Resources\PublicationIncentives\Pages;

use App\Filament\Resources\PublicationIncentives\PublicationIncentiveResource;
use App\Models\Publication;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreatePublicationIncentive extends CreateRecord
{
    protected static string $resource = PublicationIncentiveResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate total matches authors sum
        $authorsSum = collect($data['author_incentives'] ?? [])->sum('incentive_amount');
        $total = (float) ($data['total_amount'] ?? 0);

        if (bccomp((string) $total, (string) $authorsSum, 2) !== 0) {
            Notification::make()
                ->title('Validation Error')
                ->body("Total amount (৳{$total}) must equal sum of author incentives (৳{$authorsSum})")
                ->danger()
                ->send();

            $this->halt();
        }

        // Remove author_incentives from main data (will handle in afterCreate)
        $this->authorIncentives = $data['author_incentives'] ?? [];
        unset($data['author_incentives']);

        return $data;
    }

    protected array $authorIncentives = [];

    protected function afterCreate(): void
    {
        $publication = $this->record->publication;

        // Update each author's incentive_amount in pivot table
        foreach ($this->authorIncentives as $author) {
            $publication->teachers()->updateExistingPivot(
                $author['teacher_id'],
                ['incentive_amount' => $author['incentive_amount']]
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
