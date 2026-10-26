<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * The permission package's permission, with its creation, renaming and removal
 * recorded. Which role holds it is recorded on the role, by App\Models\Role.
 */
class Permission extends SpatiePermission
{
    use Concerns\RecordsActivity;
}
