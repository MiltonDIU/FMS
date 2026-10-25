<?php

namespace App\Filament\Resources\Feedback\Pages;

use App\Filament\Resources\Feedback\FeedbackResource;
use App\Models\Feedback;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    /** Nothing is created here: an item exists because a user sent it. */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /** The question the inbox is opened to answer first: what is waiting on us. */
    public function getTabs(): array
    {
        return [
            'waiting' => Tab::make('Needs our answer')
                ->badge(fn (): int => Feedback::query()->waitingOnStaff()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->waitingOnStaff()),
            'open' => Tab::make('All open')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotIn('status', Feedback::FINISHED)),
            'checking' => Tab::make('Waiting for the user to check')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', Feedback::STATUS_RESOLVED)),
            'finished' => Tab::make('Closed')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', Feedback::FINISHED)),
            'all' => Tab::make('All'),
        ];
    }
}
