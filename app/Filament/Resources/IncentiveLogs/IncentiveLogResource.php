<?php

namespace App\Filament\Resources\IncentiveLogs;

use App\Filament\Resources\IncentiveLogs\Pages;
use App\Filament\Resources\IncentiveLogs\Tables\IncentiveLogsTable;
use App\Models\IncentiveLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class IncentiveLogResource extends Resource
{
    protected static ?string $model = IncentiveLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static UnitEnum|string|null $navigationGroup = 'Publications';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Incentive Logs';

    protected static ?string $pluralLabel = 'Incentive Logs';

    protected static ?string $modelLabel = 'Incentive Log';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return IncentiveLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIncentiveLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
