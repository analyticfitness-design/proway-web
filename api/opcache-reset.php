<?php
declare(strict_types=1);

// Only allow from localhost or with deploy secret
$allowed = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    || ($_SERVER['HTTP_X_DEPLOY_SECRET'] ?? '') === ($_ENV['JWT_SECRET'] ?? '---none---');

if (!$allowed) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$result = function_exists('opcache_reset') ? opcache_reset() : false;

echo json_encode([
    'opcache_reset' => $result ? 'ok' : 'unavailable',
    'timestamp'     => date('c'),
]);
