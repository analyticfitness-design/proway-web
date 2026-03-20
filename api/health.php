<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$checks = [];

// DB connectivity check
try {
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $env = parse_ini_file($envFile);
        if ($env !== false) {
            foreach ($env as $k => $v) { $_ENV[$k] = $v; }
        }
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_NAME'] ?? 'prowaylab_db'
    );
    $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? '', $_ENV['DB_PASS'] ?? '');
    $pdo->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (\Exception $e) {
    $checks['database'] = 'error';
}

// PHP version check
$checks['php']       = PHP_VERSION;
$checks['opcache']   = extension_loaded('Zend OPcache') ? 'enabled' : 'disabled';

$allOk = !in_array('error', $checks, true);

http_response_code($allOk ? 200 : 503);
echo json_encode([
    'status'    => $allOk ? 'ok' : 'degraded',
    'timestamp' => date('c'),
    'version'   => '1.1.0',
    'deploy_id' => 'beebe48',
    'checks'    => $checks,
], JSON_PRETTY_PRINT);
