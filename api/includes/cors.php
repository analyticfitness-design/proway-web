<?php
declare(strict_types=1);

// Load .env early so APP_ENV is available for CORS checks.
// config/database.php does the same with a static-guard, so double-loading is safe.
(static function (): void {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $env = parse_ini_file($envFile);
        if ($env !== false) {
            foreach ($env as $key => $value) {
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
            }
        }
    }
})();

/**
 * Sets CORS headers for the ProWay Lab API.
 * Allows requests from the frontend domain.
 */
function setCorsHeaders(): void {
    $allowed = [
        'https://prowaylab.com',
        'https://www.prowaylab.com',
        'http://localhost',
        'http://localhost:3000',
        'http://127.0.0.1',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $appEnv = $_ENV['APP_ENV'] ?? (getenv('APP_ENV') ?: 'production');

    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } elseif ($origin === '' && $appEnv !== 'production') {
        // Direct server-to-server or curl without Origin header — allow in dev only
    } else {
        error_log('[CORS] Rejected origin: ' . $origin);
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Origin not allowed']);
        exit;
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

setCorsHeaders();
