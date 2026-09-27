<?php

namespace App\Filament\Resources\EmailSuppressions;

use App\Filament\Resources\EmailSuppressions\Pages\ManageEmailSuppressions;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\EmailSuppression;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The addresses the system will not email: bounced, fake, or asked to stop.
 *
 * Filled from here or, more usually, from a recipient row in a delivery report
 * (Sent Emails) with "Mark as bounced". Removing an entry lets mail reach the
 * address again, which is the thing to do once it has been corrected.
 */
class EmailSuppressionResource extends Resource
{
    protected static ?string $model = EmailSuppression::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static UnitEnum|string|null $navigationGroup = 'Settings & System';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Blocked Emails';

    protected static ?string $pluralLabel = 'Blocked Emails';

    protected static ?string $modelLabel = 'Blocked Email';

    protected static ?string $slug = 'blocked-emails';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique('email_suppressions', 'email', ignoreRecord: true)
                    // The address is what the entry is; change it by removing
                    // this one and adding the right one.
                    ->disabledOn('edit')
                    ->columnSpanFull(),

                Select::make('reason')
                    ->label('Reason')
                    ->options(EmailSuppression::REASONS)
                    ->default(EmailSuppression::REASON_BOUNCED)
                    ->required()
                    ->columnSpanFull(),

                Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->maxLength(2000)
                    ->placeholder('e.g. "Mailbox does not exist" from the bounce message')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EmailSuppression::REASONS[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        EmailSuppression::REASON_BOUNCED => 'danger',
                        EmailSuppression::REASON_INVALID => 'warning',
                        EmailSuppression::REASON_COMPLAINT => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('teacher.full_name')
                    ->label('Teacher')
                    ->placeholder('Not matched')
                    ->url(fn (EmailSuppression $record): ?string => $record->teacher_id
                        ? TeacherResource::getUrl('view', ['record' => $record->teacher_id])
                        : null),

                TextColumn::make('note')
                    ->label('Note')
                    ->placeholder('—')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('addedBy.name')
                    ->label('Added by')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->label('Reason')
                    ->options(EmailSuppression::REASONS),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Unblock')
                    ->modalHeading('Unblock this address?')
                    ->modalDescription('Email will be sent to it again. Do this once the address has been corrected or confirmed to work.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Unblock selected'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailSuppressions::route('/'),
        ];
    }
}
