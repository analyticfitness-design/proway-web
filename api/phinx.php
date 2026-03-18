<?php
declare(strict_types=1);

// Load .env if present (local dev)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    if ($env !== false) {
        foreach ($env as $k => $v) { $_ENV[$k] = $v; }
    }
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host'    => $_ENV['DB_HOST'] ?? 'localhost',
            'name'    => $_ENV['DB_NAME'] ?? 'prowaylab_db',
            'user'    => $_ENV['DB_USER'] ?? 'proway',
            'pass'    => $_ENV['DB_PASS'] ?? '',
            'port'    => '3306',
            'charset' => 'utf8mb4',
        ],
        'production' => [
            'adapter' => 'mysql',
            'host'    => $_ENV['DB_HOST'] ?? '',
            'name'    => $_ENV['DB_NAME'] ?? '',
            'user'    => $_ENV['DB_USER'] ?? '',
            'pass'    => $_ENV['DB_PASS'] ?? '',
            'port'    => '3306',
            'charset' => 'utf8mb4',
        ],
    ],
    'version_order' => 'creation',
];
