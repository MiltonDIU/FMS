<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Faculty;
use App\Models\Department;
use App\Models\AdministrativeRole;
use Illuminate\Support\Facades\DB;

$fac = Faculty::where('short_name', 'fsit')->first();
$dept = $fac ? Department::where('code', 'mct')->where('faculty_id', $fac->id)->first() : null;

echo "FAC: " . ($fac ? $fac->id . ' (' . $fac->short_name . ')' : 'NOT FOUND') . "\n";
echo "DEPT: " . ($dept ? $dept->id . ' (' . $dept->code . ')' : 'NOT FOUND') . "\n\n";

if ($dept) {
    echo "=== administrative_role_user rows touching dept $dept->id or faculty " . ($fac ? $fac->id : '?') . " ===\n";
    $rows = DB::table('administrative_role_user as aru')
        ->leftJoin('administrative_roles as ar', 'ar.id', '=', 'aru.administrative_role_id')
        ->leftJoin('teachers as t', 't.user_id', '=', 'aru.user_id')
        ->leftJoin('departments as d', 'd.id', '=', 't.department_id')
        ->where(function ($q) use ($dept, $fac) {
            $q->where('aru.department_id', $dept->id)
              ->orWhere('aru.faculty_id', $fac ? $fac->id : null);
        })
        ->select('aru.id', 'aru.user_id', 'aru.administrative_role_id', 'ar.name as role', 'aru.department_id', 'aru.faculty_id', 'aru.is_active', 'aru.deleted_at', 't.id as teacher_id', 'd.code as teacher_dept')
        ->get();
    foreach ($rows as $r) {
        echo "role={$r->role} | aru.dept_id={$r->department_id} aru.fac_id={$r->faculty_id} is_active=" . var_export($r->is_active, true) . " deleted_at=" . ($r->deleted_at ?? 'NULL') . " | teacher#{$r->teacher_id} home_dept={$r->teacher_dept}\n";
    }
    echo "\nCount: " . $rows->count() . "\n";
}
