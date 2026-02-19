<?php
declare(strict_types=1);

define('DB_HOST',    'proway-lab_mysql-db');
define('DB_NAME',    'prowaylab_db');
define('DB_USER',    'proway');
define('DB_PASS',    '7a818f448ee3ce4bc0d0');
define('DB_CHARSET', 'utf8mb4');

// Token expiry in hours
define('TOKEN_EXPIRY_CLIENT', 168);  // 7 días
define('TOKEN_EXPIRY_ADMIN',  8);    // 8 horas

define('API_SECRET', 'prowaylab_secret_key_change_this');

// PayU Colombia (COP)
define('PAYU_MERCHANT_ID',    'YOUR_MERCHANT_ID');
define('PAYU_API_KEY',        'YOUR_API_KEY');
define('PAYU_ACCOUNT_ID_COP', 'YOUR_ACCOUNT_ID');
define('PAYU_TEST_MODE',      true);

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
