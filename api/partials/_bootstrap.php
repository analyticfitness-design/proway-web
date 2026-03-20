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
use ProWay\Domain\Notification\NotificationService;
use ProWay\Domain\Notification\MySQLNotificationRepository;
use ProWay\Domain\ActivityLog\ActivityLogService;
use ProWay\Domain\ActivityLog\MySQLActivityLogRepository;
use ProWay\Domain\ErrorLog\ErrorLogService;
use ProWay\Domain\ErrorLog\MySQLErrorLogRepository;
use ProWay\Domain\SocialMetrics\MySQLSocialProfileRepository;
use ProWay\Domain\SocialMetrics\MySQLSocialPostRepository;
use ProWay\Domain\SocialMetrics\MySQLMetricsRepository;
use ProWay\Domain\SocialMetrics\SocialMetricsService;

$pdo            = Connection::getInstance();
$tokens         = new TokenManager($pdo);
$auth           = new AuthService($pdo, $tokens);
$clientService  = new ClientService(new MySQLClientRepository($pdo));
$projectService = new ProjectService(new MySQLProjectRepository($pdo));
$invoiceService = new InvoiceService(new MySQLInvoiceRepository($pdo));
$notifService   = new NotificationService(new MySQLNotificationRepository($pdo));
$activityService = new ActivityLogService(new MySQLActivityLogRepository($pdo));
$errorLogService = new ErrorLogService(new MySQLErrorLogRepository($pdo));
$socialMetricsService = new SocialMetricsService(
    new MySQLSocialProfileRepository($pdo),
    new MySQLSocialPostRepository($pdo),
    new MySQLMetricsRepository($pdo),
);
