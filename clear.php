<?php

// Upload this file to your Laravel project root, then open:
// https://nafix.site/Mapp/clear.php?key=clear123
//
// Delete this file after running it.

$secret = 'clear123';

if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    http_response_code(403);
    exit('Forbidden');
}

define('LARAVEL_START', microtime(true));

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$commands = [
    'optimize:clear',
    'config:clear',
    'cache:clear',
    'route:clear',
    'view:clear',
];

header('Content-Type: text/plain; charset=UTF-8');

foreach ($commands as $command) {
    try {
        $kernel->call($command);
        echo $command . " OK\n";
        echo trim($kernel->output()) . "\n\n";
    } catch (Throwable $e) {
        echo $command . " FAILED\n";
        echo $e->getMessage() . "\n\n";
    }
}

echo "Done. Delete clear.php now.\n";
