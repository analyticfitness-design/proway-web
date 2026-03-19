<?php
declare(strict_types=1);

// _auth.php — requires _bootstrap.php to have been loaded first.
// Reads the httpOnly cookie, validates the token, and exposes $currentUser.
// On failure, outputs a 401 HTML fragment and exits.

use ProWay\Domain\Auth\UserDTO;

$token = $_COOKIE['pw_access'] ?? '';

if ($token === '') {
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    echo '<div class="alert alert--error">No autenticado. Por favor inicia sesión.</div>';
    exit;
}

/** @var \ProWay\Domain\Auth\AuthService $auth */
$currentUser = $auth->getCurrentUser($token);

if (!($currentUser instanceof UserDTO)) {
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    echo '<div class="alert alert--error">Sesión inválida o expirada. Por favor inicia sesión nuevamente.</div>';
    exit;
}
