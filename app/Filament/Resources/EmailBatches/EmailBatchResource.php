<?php

namespace App\Filament\Resources\EmailBatches;

use App\Filament\Resources\EmailBatches\Pages;
use App\Filament\Resources\EmailBatches\RelationManagers\RecipientsRelationManager;
use App\Filament\Resources\EmailBatches\Schemas\EmailBatchInfolist;
use App\Filament\Resources\EmailBatches\Tables\EmailBatchesTable;
use App\Models\EmailBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * What has been emailed to teachers, and what became of it.
 *
 * Read-only, and there is no form: a batch is written by the act of sending and
 * its recipient rows are written by the queue, the mail server and the
 * recipients' own mail clients. The one thing that can be done from here is to
 * send again — which makes a new batch rather than changing this one, so that
 * the record of the first attempt survives the second.
 */
class EmailBatchResource extends Resource
{
    protected static ?string $model = EmailBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static UnitEnum|string|null $navigationGroup = 'Settings & System';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Sent Emails';

    protected static ?string $pluralLabel = 'Sent Emails';

    protected static ?string $modelLabel = 'Email Batch';

    protected static ?string $slug = 'sent-emails';

    public static function table(Table $table): Table
    {
        return EmailBatchesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmailBatchInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailBatches::route('/'),
            'view' => Pages\ViewEmailBatch::route('/{record}'),
        ];
    }
}
