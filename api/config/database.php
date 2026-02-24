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
define('DB_PASS',    env('DB_PASS',    '7a818f448ee3ce4bc0d0'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

define('TOKEN_EXPIRY_CLIENT', (int) env('TOKEN_EXPIRY_CLIENT', '168'));
define('TOKEN_EXPIRY_ADMIN',  (int) env('TOKEN_EXPIRY_ADMIN',  '8'));

define('API_SECRET', env('API_SECRET', 'prowaylab_secret_key_change_this'));

define('PAYU_MERCHANT_ID',    env('PAYU_MERCHANT_ID',    'YOUR_MERCHANT_ID'));
define('PAYU_API_KEY',        env('PAYU_API_KEY',        'YOUR_API_KEY'));
define('PAYU_ACCOUNT_ID_COP', env('PAYU_ACCOUNT_ID_COP', 'YOUR_ACCOUNT_ID'));
define('PAYU_TEST_MODE',      env('PAYU_TEST_MODE', 'true') === 'true');

// WhatsApp Business API (Meta)
define('WA_PHONE_NUMBER_ID', env('WA_PHONE_NUMBER_ID', '1013772675149039'));
define('WA_ACCESS_TOKEN',    env('WA_ACCESS_TOKEN',    'EAANNz5iZB4XYBQ7fgQtYsrkZCuVTsIVEdAKyZCBA5HZCtmZA75JSGo7MWmYoFJi7CQOp5GT81oUPDFO751wn7JRrRcyoZBpceYeR1iNZCXaAe3cKR3pDR5ngO48oEA8LONZAz8hL6WDC1PdJ0ZA69VrwaOHinJp2WE1MZCBrBcAmywuk2ME6RUPSSRx4jHkbAMmn1Q7Be4D51jC4OcgA4P'));
define('N8N_API_KEY',        env('N8N_API_KEY',        'proway-n8n-2026'));

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
