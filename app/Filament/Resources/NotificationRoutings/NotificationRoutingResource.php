<?php

namespace App\Filament\Resources\NotificationRoutings;

use App\Filament\Resources\NotificationRoutings\Pages\CreateNotificationRouting;
use App\Filament\Resources\NotificationRoutings\Pages\EditNotificationRouting;
use App\Filament\Resources\NotificationRoutings\Pages\ListNotificationRoutings;
use App\Filament\Resources\NotificationRoutings\Schemas\NotificationRoutingForm;
use App\Filament\Resources\NotificationRoutings\Tables\NotificationRoutingsTable;
use App\Models\NotificationRouting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NotificationRoutingResource extends Resource
{
    protected static ?string $model = NotificationRouting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'trigger_section';

    public static function form(Schema $schema): Schema
    {
        return NotificationRoutingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificationRoutingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationRoutings::route('/'),
            'create' => CreateNotificationRouting::route('/create'),
            'edit' => EditNotificationRouting::route('/{record}/edit'),
        ];
    }
}
