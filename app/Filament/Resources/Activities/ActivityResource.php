<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages;
use App\Filament\Resources\Activities\Schemas\ActivityInfolist;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Activity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The audit trail, read-only.
 *
 * There is no form and no create or edit page on purpose: an entry records
 * something that already happened, and the policy refuses every write for
 * everyone. Old entries are removed by the scheduled activitylog:clean, not by
 * hand.
 *
 * Access is by permission (ViewAny:Activity, View:Activity) rather than by role,
 * so it normally sits with super_admin but can be granted to someone else from
 * the role editor without a deployment.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Settings & System';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $pluralLabel = 'Activity Log';

    protected static ?string $modelLabel = 'Activity';

    protected static ?string $slug = 'activity-log';

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'view' => Pages\ViewActivity::route('/{record}'),
        ];
    }

    /** Nothing writes here, so the whole resource is read-only. */
    public static function canCreate(): bool
    {
        return false;
    }
}
