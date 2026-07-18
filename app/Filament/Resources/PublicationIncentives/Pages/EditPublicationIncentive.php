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
        if ($publication) {
            $pivots = \DB::table('publication_authors')
                ->where('publication_id', $publication->id)
                ->get();

            $teacherIds = $pivots->where('authorable_type', \App\Models\Teacher::class)->pluck('authorable_id');
            $authorIds = $pivots->where('authorable_type', \App\Models\Author::class)->pluck('authorable_id');

            $teachers = \App\Models\Teacher::whereIn('id', $teacherIds)->get()->keyBy('id');
            $authors = \App\Models\Author::whereIn('id', $authorIds)->get()->keyBy('id');

            $data['author_incentives'] = $pivots->map(function ($pivot) use ($teachers, $authors) {
                $name = 'Unknown';
                if ($pivot->authorable_type === \App\Models\Teacher::class) {
                    $model = $teachers->get($pivot->authorable_id);
                    $name = $model ? trim("{$model->first_name} {$model->middle_name} {$model->last_name}") : 'Unknown';
                } elseif ($pivot->authorable_type === \App\Models\Author::class) {
                    $model = $authors->get($pivot->authorable_id);
                    $name = $model ? $model->name : 'Unknown';
                }

                $rolePriority = match ($pivot->author_role) {
                    'first' => 1,
                    'corresponding' => 2,
                    default => 3,
                };

                return [
                    'id' => $pivot->id,
                    'author_name' => $name,
                    'author_role' => $pivot->author_role,
                    'incentive_amount' => $pivot->incentive_amount ?? 0,
                    'priority' => sprintf('%d-%04d', $rolePriority, $pivot->sort_order),
                ];
            })->sortBy('priority')->values()->toArray();
        }

        return $data;
    }

    protected array $authorIncentives = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate total matches authors sum
        $authorsSum = collect($data['author_incentives'] ?? [])->sum('incentive_amount');
        $total = (float) ($data['total_amount'] ?? 0);

        if (round((float) $total, 2) !== round((float) $authorsSum, 2)) {
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
        // Update each author's incentive_amount in pivot table
        foreach ($this->authorIncentives as $author) {
            if (!empty($author['id'])) {
                \DB::table('publication_authors')
                    ->where('id', $author['id'])
                    ->update(['incentive_amount' => $author['incentive_amount']]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
