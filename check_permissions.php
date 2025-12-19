<?php
$permissions = \Spatie\Permission\Models\Permission::all()->pluck('name');
echo "Permissions:\n";
foreach ($permissions as $permission) {
    echo "- $permission\n";
}
