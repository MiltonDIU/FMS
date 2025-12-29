<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$m = new \App\Models\JobExperience();
echo "Class: " . get_class($m) . "\n";
echo "Method 'country' exists: " . (method_exists($m, 'country') ? 'YES' : 'NO') . "\n";

try {
    $r = $m->country();
    echo "Relation: " . get_class($r) . "\n";
} catch (\Throwable $e) {
    echo "Error calling country(): " . $e->getMessage() . "\n";
}
