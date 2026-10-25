<?php

namespace App\Filament\Resources\MyFeedback;

use App\Filament\Resources\Feedback\Support\FeedbackAttachments;
use App\Filament\Resources\MyFeedback\Pages;
use App\Models\Feedback;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Where a user reports a problem or asks for something, and follows the answer.
 *
 * Open to anybody signed in while "Feedback" is switched on under System
 * Settings, and to nobody when it is off — the menu disappears and the pages
 * refuse. Deliberately not a Shield permission: deploys run no seeders, and
 * nobody should need one to tell us something is broken.
 *
 * Only the viewer's own items are ever in the query, so another person's
 * thread is not found rather than forbidden.
 */
class MyFeedbackResource extends Resource
{
    protected static ?string $model = Feedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Feedback';

    protected static ?string $pluralLabel = 'My Feedback';

    protected static ?string $modelLabel = 'Feedback';

    protected static ?string $slug = 'my-feedback';

    public static function canViewAny(): bool
    {
        return Auth::check() && Feedback::isEnabled();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && (int) $record->user_id === (int) Auth::id();
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id() ?? 0);
    }

    /** Answers from the team that the user has not acted on yet. */
    public static function getNavigationBadge(): ?string
    {
        $waiting = static::getEloquentQuery()->waitingOnSubmitter()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'The team has answered';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()
                ->description('Tell us about a problem you ran into, or something you would like the system to do. The team will answer here, and tell you when it is done so you can check it.')
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('What is it about?')
                        ->options(Feedback::TYPES)
                        ->default('bug')
                        ->required()
                        ->native(false),

                    TextInput::make('subject')
                        ->label('Subject')
                        ->required()
                        ->maxLength(200)
                        ->placeholder('e.g. "Publication save button gives an error"'),

                    Textarea::make('message')
                        ->label('Details')
                        ->required()
                        ->rows(8)
                        ->maxLength(10000)
                        ->columnSpanFull()
                        ->placeholder("For a problem: what you did, what you expected, and what happened instead.\nFor an idea: what you would like, and how it would help."),

                    TextInput::make('page_url')
                        ->label('Page where it happened')
                        ->helperText('Filled in with the page you came from. Clear it if it is not related.')
                        ->url()
                        ->maxLength(1000)
                        ->columnSpanFull()
                        ->default(fn (): ?string => static::previousPanelUrl()),

                    FeedbackAttachments::field()->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->formatStateUsing(fn (Feedback $record): string => $record->reference()),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->wrap()
                    ->limit(80)
                    ->description(fn (Feedback $record): string => Feedback::TYPES[$record->type] ?? $record->type)
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Feedback::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => Feedback::statusColour($state)),

                TextColumn::make('waiting_on')
                    ->label('')
                    ->badge()
                    ->formatStateUsing(fn (Feedback $record): ?string => match (true) {
                        $record->isFinished() => null,
                        $record->status === Feedback::STATUS_RESOLVED => 'Please check',
                        $record->waiting_on === Feedback::WAITING_ON_SUBMITTER => 'New reply',
                        default => null,
                    })
                    ->color('success'),

                TextColumn::make('last_activity_at')
                    ->label('Last update')
                    ->since()
                    ->dateTimeTooltip('d M Y, h:i A'),

                TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('d M Y'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('You have not sent any feedback yet')
            ->emptyStateDescription('Found a problem, or have an idea? Use the button above to tell us.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(fn (Feedback $record): string => $record->reference() . ' — ' . $record->subject)
                ->columns(3)
                ->schema([
                    TextEntry::make('type')
                        ->label('Type')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn (string $state): string => Feedback::TYPES[$state] ?? $state),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => Feedback::STATUSES[$state] ?? $state)
                        ->color(fn (string $state): string => Feedback::statusColour($state))
                        ->helperText(fn (Feedback $record): ?string => $record->status === Feedback::STATUS_RESOLVED
                            ? 'The team says this is done. Please check it, then use the buttons above to tell us whether it works.'
                            : null),

                    TextEntry::make('created_at')
                        ->label('Sent')
                        ->dateTime('d M Y, h:i A'),
                ]),

            Section::make('Conversation')
                ->schema([
                    ViewEntry::make('thread')
                        ->hiddenLabel()
                        ->view('filament.feedback.thread', ['staffView' => false]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMyFeedback::route('/'),
            'create' => Pages\CreateMyFeedback::route('/create'),
            'view' => Pages\ViewMyFeedback::route('/{record}'),
        ];
    }

    /**
     * The panel page the user came from, if they came from one.
     *
     * Not this resource's own pages: arriving from the feedback list says
     * nothing about where the problem is.
     */
    protected static function previousPanelUrl(): ?string
    {
        $previous = url()->previous();

        if (! str_starts_with($previous, url('/admin')) || str_contains($previous, '/admin/my-feedback')) {
            return null;
        }

        return $previous;
    }
}
