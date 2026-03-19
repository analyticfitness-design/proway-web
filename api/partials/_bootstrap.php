<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ProWay\Infrastructure\Database\Connection;
use ProWay\Domain\Auth\TokenManager;
use ProWay\Domain\Auth\AuthService;
use ProWay\Domain\Client\ClientService;
use ProWay\Domain\Client\MySQLClientRepository;
use ProWay\Domain\Project\ProjectService;
use ProWay\Domain\Project\MySQLProjectRepository;
use ProWay\Domain\Invoice\InvoiceService;
use ProWay\Domain\Invoice\MySQLInvoiceRepository;

$pdo            = Connection::getInstance();
$tokens         = new TokenManager($pdo);
$auth           = new AuthService($pdo, $tokens);
$clientService  = new ClientService(new MySQLClientRepository($pdo));
$projectService = new ProjectService(new MySQLProjectRepository($pdo));
$invoiceService = new InvoiceService(new MySQLInvoiceRepository($pdo));
