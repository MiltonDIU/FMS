<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ListRoles;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoleResource extends ShieldRoleResource
{
    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 20;

    protected static ?int $navigationSort = 2; // Roles will appear after Users

    /**
     * Shield's own table, plus how many people hold each role.
     *
     * How many users a role carries belongs on the row it describes, not in a
     * panel of its own. A widget would be a second list of the same nine roles,
     * free to disagree with the one underneath it the moment either changes, and
     * it could not be sorted or searched with the rest of the table.
     *
     * Built by pushing onto Shield's table rather than replacing it. Copying
     * their columns here to append one would freeze this list at the version we
     * copied — a column they add or relabel in an upgrade would silently never
     * arrive.
     */
    public static function table(Table $table): Table
    {
        return parent::table($table)->pushColumns([
            TextColumn::make('users_count')
                ->label('Users')
                ->counts('users')
                ->badge()
                /*
                 * A role nobody holds is worth seeing at a glance: it is either
                 * a draft, or one whose holders were moved and never replaced.
                 * Neither is wrong, but both are worth a second look.
                 */
                ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray')
                ->sortable(),
        ]);
    }

    /**
     * Shield's pages, with the list swapped for one bound to this resource.
     *
     * Only the index changes. The create, view and edit pages read no table, so
     * they can stay Shield's and keep whatever those pages gain in an upgrade.
     */
    public static function getPages(): array
    {
        return array_merge(parent::getPages(), [
            'index' => ListRoles::route('/'),
        ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Guard' => $record->guard_name,
            'Permissions' => $record->permissions->count() . ' permissions',
        ];
    }
}
