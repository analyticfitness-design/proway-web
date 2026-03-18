<?php
declare(strict_types=1);

// PSR-4 autoloader via Composer
$autoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}
