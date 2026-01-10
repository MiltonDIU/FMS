<?php

namespace App\Filament\Resources\PublicationIncentives\Pages;

use App\Filament\Resources\PublicationIncentives\PublicationIncentiveResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPublicationIncentive extends EditRecord
{
    protected static string $resource = PublicationIncentiveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load author incentives from pivot table
        $publication = $this->record->publication;

        $data['author_incentives'] = $publication->teachers
            ->sortBy('pivot.sort_order')
            ->map(fn($t) => [
                'teacher_id' => $t->id,
                'author_role' => $t->pivot->author_role,
                'incentive_amount' => $t->pivot->incentive_amount ?? 0,
            ])->toArray();

        return $data;
    }

    protected array $authorIncentives = [];

    protected function mutateFormDataBeforeSave(array $data): array
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

        // Store for afterSave
        $this->authorIncentives = $data['author_incentives'] ?? [];
        unset($data['author_incentives']);

        $oldStatus = $this->record->status;
        $newStatus = $data['status'];

        // Handle status changes with timestamp management
        if ($newStatus === 'approved') {
            // Set approved timestamp if not already set
            if (!$this->record->approved_at) {
                $data['approved_at'] = now();
                $data['approved_by'] = auth()->id();
            }
            // Reset paid timestamps if reverting from paid
            if ($oldStatus === 'paid') {
                $data['paid_at'] = null;
                $data['paid_by'] = null;
            }
        } elseif ($newStatus === 'paid') {
            // Set paid timestamp if not already set
            if (!$this->record->paid_at) {
                $data['paid_at'] = now();
                $data['paid_by'] = auth()->id();
            }
            // Set approved timestamp if not already set
            if (!$this->record->approved_at) {
                $data['approved_at'] = now();
                $data['approved_by'] = auth()->id();
            }
        } elseif ($newStatus === 'pending') {
            // Reset all timestamps when reverting to pending
            $data['approved_at'] = null;
            $data['approved_by'] = null;
            $data['paid_at'] = null;
            $data['paid_by'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
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
