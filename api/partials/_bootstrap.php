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
use ProWay\Domain\Message\MessageService;
use ProWay\Domain\Message\MySQLMessageRepository;
use ProWay\Domain\Analytics\AnalyticsService;
use ProWay\Domain\Analytics\MySQLAnalyticsRepository;
use ProWay\Domain\Approval\ApprovalService;
use ProWay\Domain\Approval\MySQLApprovalRepository;
use ProWay\Domain\Brief\BriefService;
use ProWay\Domain\Brief\MySQLBriefRepository;
use ProWay\Domain\ContentCalendar\ContentCalendarService;
use ProWay\Domain\ContentCalendar\MySQLContentSlotRepository;
use ProWay\Domain\Survey\MySQLSurveyRepository;
use ProWay\Domain\Survey\SurveyService;
use ProWay\Domain\WhatsApp\WhatsAppNotifier;
use ProWay\Infrastructure\WhatsApp\WhatsAppService;

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
$messageService  = new MessageService(new MySQLMessageRepository($pdo));
$approvalService  = new ApprovalService(new MySQLApprovalRepository($pdo));
$briefService     = new BriefService(new MySQLBriefRepository($pdo));
$analyticsService = new AnalyticsService(new MySQLAnalyticsRepository($pdo));
$contentCalendarService = new ContentCalendarService(new MySQLContentSlotRepository($pdo));
$surveyService          = new SurveyService(new MySQLSurveyRepository($pdo));

// WhatsApp Business API
$whatsAppService  = new WhatsAppService();
$whatsAppNotifier = new WhatsAppNotifier($whatsAppService, $clientService);
