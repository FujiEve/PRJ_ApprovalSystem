<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';

    (require_once __DIR__ . '/../bootstrap/app.php')
        ->handleRequest(Request::capture());
    return;
}

require_once __DIR__ . '/../app/Services/StandalonePdoKernel.php';
$kernel = new \App\Services\StandalonePdoKernel();
$kernel->handleHttpRequest();

<?php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'message' => 'Backend (Nginx + PHP-FPM) is running.',
], JSON_UNESCAPED_UNICODE);

