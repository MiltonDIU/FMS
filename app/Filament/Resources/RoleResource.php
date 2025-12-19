<?php

namespace App\Filament\Resources;

use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;

class RoleResource extends ShieldRoleResource
{
    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 20;

    protected static ?int $navigationSort = 2; // Roles will appear after Users

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
