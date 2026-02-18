<?php
declare(strict_types=1);

/**
 * Send a successful JSON response and exit.
 */
function respond(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send an error JSON response and exit.
 */
function respondError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Decode JSON request body into an array.
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? [];
}

/**
 * Abort with 405 if request method does not match.
 */
function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        respondError('Method not allowed', 405);
    }
}

/**
 * Accept any of the given HTTP methods; abort with 405 otherwise.
 */
function requireMethods(array $methods): string {
    $current = $_SERVER['REQUEST_METHOD'];
    $upper   = array_map('strtoupper', $methods);
    if (!in_array($current, $upper, true)) {
        respondError('Method not allowed. Allowed: ' . implode(', ', $upper), 405);
    }
    return $current;
}
