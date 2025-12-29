<?php

namespace App\Filament\Resources\TeacherVersions;

use App\Filament\Resources\TeacherVersions\Pages\CreateTeacherVersion;
use App\Filament\Resources\TeacherVersions\Pages\EditTeacherVersion;
use App\Filament\Resources\TeacherVersions\Pages\ListTeacherVersions;
use App\Filament\Resources\TeacherVersions\Schemas\TeacherVersionForm;
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

    // Show badge with pending count
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return TeacherVersionForm::configure($schema);
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
            'create' => CreateTeacherVersion::route('/create'),
            'edit' => EditTeacherVersion::route('/{record}/edit'),
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
