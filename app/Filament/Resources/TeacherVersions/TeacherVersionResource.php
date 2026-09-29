<?php

namespace App\Filament\Resources\TeacherVersions;

use App\Filament\Resources\TeacherVersions\Pages\ListTeacherVersions;
use App\Filament\Resources\TeacherVersions\Pages\ViewTeacherVersion;
use App\Filament\Resources\TeacherVersions\Schemas\TeacherVersionInfolist;
use App\Filament\Resources\TeacherVersions\Tables\TeacherVersionsTable;
use App\Models\TeacherVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
class TeacherVersionResource extends Resource
{
    protected static ?string $model = TeacherVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Approvals';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pending Approvals';

    protected static ?string $pluralLabel = 'Teacher Profile Updates';

    protected static ?string $modelLabel = 'Profile Update';

    protected static ?string $recordTitleAttribute = 'change_summary';

    /**
     * Whether the signed-in user reviews everyone's changes, rather than only
     * reading the history of their own profile.
     */
    public static function reviewsAllProfiles(): bool
    {
        return (bool) auth()->user()?->can('ViewAny:TeacherVersion');
    }

    /**
     * A teacher without ViewAny:TeacherVersion is shown their own profile's
     * versions and nobody else's.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! static::reviewsAllProfiles()) {
            $query->where('teacher_id', auth()->user()?->teacher?->id ?? 0);
        }

        return $query;
    }

    public static function getNavigationLabel(): string
    {
        return static::reviewsAllProfiles() ? 'Pending Approvals' : 'My Profile History';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return static::reviewsAllProfiles() ? static::$navigationGroup : null;
    }

    // Show badge with pending count
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Read-only. A version records what happened to a profile; there is no
     * create or edit page, so the record cannot be rewritten afterwards.
     */
    public static function infolist(Schema $schema): Schema
    {
        return TeacherVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeacherVersionsTable::configure($table);
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
            'index' => ListTeacherVersions::route('/'),
            'view' => ViewTeacherVersion::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
