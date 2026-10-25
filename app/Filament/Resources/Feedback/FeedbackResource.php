<?php

namespace App\Filament\Resources\Feedback;

use App\Filament\Resources\Feedback\Pages;
use App\Filament\Resources\Feedback\Schemas\FeedbackInfolist;
use App\Filament\Resources\Feedback\Tables\FeedbackTable;
use App\Models\Feedback;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The team's side of feedback: everything users have sent, and the answers.
 *
 * No create or edit: an item is written by the person who sent it, and every
 * change after that is a reply on the view page, so the thread keeps the record
 * of who did what. Not affected by the System Settings switch — turning
 * feedback off stops new items, not the team's access to the ones already in.
 */
class FeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static UnitEnum|string|null $navigationGroup = 'Settings & System';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Feedback Inbox';

    protected static ?string $pluralLabel = 'Feedback Inbox';

    protected static ?string $modelLabel = 'Feedback';

    protected static ?string $slug = 'feedback-inbox';

    protected static ?string $recordTitleAttribute = 'subject';

    /** Items waiting on the team's answer. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = Feedback::query()->waitingOnStaff()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Waiting for an answer from the team';
    }

    public static function table(Table $table): Table
    {
        return FeedbackTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FeedbackInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeedback::route('/'),
            'view' => Pages\ViewFeedback::route('/{record}'),
        ];
    }
}
