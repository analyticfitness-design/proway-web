<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');

// Clear the httpOnly cookie
setcookie('pw_access', '', [
    'expires'  => time() - 3600,
    'path'     => '/api',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);

$token = getBearerToken();
if (!$token) {
    respondError('No token provided', 400);
}

revokeToken($token);

respond(['message' => 'Sesión cerrada correctamente']);
