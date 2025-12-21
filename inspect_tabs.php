<?php

require __DIR__ . '/vendor/autoload.php';

use Filament\Schemas\Components\Tabs;

$reflection = new ReflectionClass(Tabs::class);
$methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

foreach ($methods as $method) {
    echo $method->getName() . "\n";
}
