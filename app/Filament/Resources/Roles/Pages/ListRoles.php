<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles as ShieldListRoles;

/**
 * Shield's role list, bound to our RoleResource instead of theirs.
 *
 * Subclassing RoleResource alone was not enough. A Filament list page reads its
 * table from the resource named in its own `$resource` property, and Shield's
 * page names Shield's resource — so our table() override was registered on the
 * panel, inherited correctly, and never called. The list rendered without the
 * extra column and without an error to say why.
 *
 * This exists only to repoint that property. Everything else is Shield's.
 */
class ListRoles extends ShieldListRoles
{
    protected static string $resource = RoleResource::class;
}
