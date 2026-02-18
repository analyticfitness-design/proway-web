<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

requireMethod('POST');

$token = getBearerToken();
if (!$token) {
    respondError('No token provided', 400);
}

revokeToken($token);

respond(['message' => 'Sesión cerrada correctamente']);
