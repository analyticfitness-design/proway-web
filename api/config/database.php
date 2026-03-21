<?php
declare(strict_types=1);

// Helper: check $_ENV, getenv(), then fallback
function env(string $key, string $fallback = ''): string {
    return $_ENV[$key] ?? (getenv($key) ?: $fallback);
}

// Load .env file (local dev). In Docker/EasyPanel, env vars come from container config.
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    if ($env !== false) {
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
        }
    }
}

define('DB_HOST',    env('DB_HOST',    'proway-lab_mysql-db'));
define('DB_NAME',    env('DB_NAME',    'prowaylab_db'));
define('DB_USER',    env('DB_USER',    'proway'));
define('DB_PASS',    env('DB_PASS',    ''));

define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

define('TOKEN_EXPIRY_CLIENT', (int) env('TOKEN_EXPIRY_CLIENT', '168'));
define('TOKEN_EXPIRY_ADMIN',  (int) env('TOKEN_EXPIRY_ADMIN',  '8'));

define('API_SECRET', env('API_SECRET', ''));

define('PAYU_MERCHANT_ID',    env('PAYU_MERCHANT_ID',    ''));
define('PAYU_API_KEY',        env('PAYU_API_KEY',        ''));
define('PAYU_ACCOUNT_ID_COP', env('PAYU_ACCOUNT_ID_COP', ''));
define('PAYU_TEST_MODE',      env('PAYU_TEST_MODE', 'true') === 'true');

define('WA_PHONE_NUMBER_ID', env('WA_PHONE_NUMBER_ID', ''));
define('WA_ACCESS_TOKEN',    env('WA_ACCESS_TOKEN',    ''));
define('N8N_API_KEY',        env('N8N_API_KEY',        ''));
define('N8N_WEBHOOK_URL',    env('N8N_WEBHOOK_URL',    ''));

define('SOCIAL_API_PROVIDER', env('SOCIAL_API_PROVIDER', 'mock'));
define('SOCIAL_API_KEY',      env('SOCIAL_API_KEY',      ''));

define('CLAUDE_API_KEY', env('CLAUDE_API_KEY', ''));
define('CLAUDE_MODEL',   env('CLAUDE_MODEL',   'claude-sonnet-4-6'));

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
