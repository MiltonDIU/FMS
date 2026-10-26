<?php

namespace App\Models;

use App\Support\AccessAudit;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The permission package's role, with its edits and its permissions recorded.
 *
 * config/permission.php points here, so Shield's role screens and every
 * assignment go through this class. The permissions a role holds are a pivot,
 * invisible to the model events, and go through AccessAudit instead.
 */
class Role extends SpatieRole
{
    use Concerns\RecordsActivity;

    public function givePermissionTo(...$permissions): static
    {
        return AccessAudit::watch($this, 'permissions', fn () => parent::givePermissionTo(...$permissions));
    }

    public function syncPermissions(...$permissions): static
    {
        return AccessAudit::watch($this, 'permissions', fn () => parent::syncPermissions(...$permissions));
    }

    public function revokePermissionTo($permission): static
    {
        return AccessAudit::watch($this, 'permissions', fn () => parent::revokePermissionTo($permission));
    }
}
