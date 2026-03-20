<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use ProWay\Api\V1\Controller\AdminController;
use ProWay\Api\V1\Controller\AuthController;
use ProWay\Api\V1\Controller\ClientController;
use ProWay\Api\V1\Controller\InvoiceController;
use ProWay\Api\V1\Controller\PaymentController;
use ProWay\Api\V1\Controller\DeliverableController;
use ProWay\Api\V1\Controller\ErrorLogController;
use ProWay\Api\V1\Controller\NotificationController;
use ProWay\Api\V1\Controller\ProjectController;
use ProWay\Api\V1\Controller\SocialMetricsController;
use ProWay\Api\V1\Controller\MessageController;
use ProWay\Api\V1\Controller\OnboardingController;
use ProWay\Api\V1\Controller\ReportController;
use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Api\V1\Middleware\RateLimitMiddleware;
use ProWay\Domain\Auth\AuthService;
use ProWay\Domain\Auth\TokenManager;
use ProWay\Domain\Client\CachedClientRepository;
use ProWay\Domain\Client\ClientService;
use ProWay\Domain\Client\MySQLClientRepository;
use ProWay\Domain\Invoice\CachedInvoiceRepository;
use ProWay\Domain\Invoice\InvoiceService;
use ProWay\Domain\Invoice\MySQLInvoiceRepository;
use ProWay\Domain\Project\CachedProjectRepository;
use ProWay\Domain\Project\MySQLProjectRepository;
use ProWay\Domain\Project\ProjectService;
use ProWay\Domain\Deliverable\DeliverableService;
use ProWay\Domain\Deliverable\MySQLDeliverableRepository;
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
use ProWay\Domain\Payment\WompiService;
use ProWay\Infrastructure\Cache\CacheFactory;
use ProWay\Infrastructure\Database\Connection;
use ProWay\Infrastructure\Email\MailjetService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;
use ProWay\Infrastructure\Http\Router;
use ProWay\Domain\Report\MonthlyReportService;
use ProWay\Domain\Report\ReportPdfRenderer;
use ProWay\Infrastructure\Pdf\PdfRenderer;

// ── CORS (existing logic preserved) ──────────────────────────────────────────
$allowedOrigins = array_filter(explode(',', getenv('ALLOWED_ORIGINS') ?: ''));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} elseif (!empty($allowedOrigins) && !empty($origin)) {
    error_log('[CORS] Rejected origin: ' . $origin);
    http_response_code(403);
    exit;
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
$pdo    = Connection::getInstance();
$tokens = new TokenManager($pdo);
$auth   = new AuthService($pdo, $tokens);
$mw     = new AuthMiddleware($auth);

$cache = CacheFactory::create();

$clientService  = new ClientService(new CachedClientRepository(new MySQLClientRepository($pdo), $cache));
$projectService = new ProjectService(new CachedProjectRepository(new MySQLProjectRepository($pdo), $cache));
$invoiceService = new InvoiceService(new CachedInvoiceRepository(new MySQLInvoiceRepository($pdo), $cache));

$deliverableService  = new DeliverableService(new MySQLDeliverableRepository($pdo));
$notificationService = new NotificationService(new MySQLNotificationRepository($pdo));
$activityLogService  = new ActivityLogService(new MySQLActivityLogRepository($pdo));
$errorLogService     = new ErrorLogService(new MySQLErrorLogRepository($pdo));

$wompi   = new WompiService();
$mailer  = new MailjetService();

$authCtrl    = new AuthController($auth, $mw, $mailer);
$clientCtrl  = new ClientController($clientService, $mw);
$projectCtrl = new ProjectController($projectService, $mw, $activityLogService);
$pdfRenderer = new PdfRenderer();
$invoiceCtrl = new InvoiceController($invoiceService, $mw, $clientService, $pdfRenderer);
$paymentCtrl = new PaymentController($invoiceService, $clientService, $wompi, $mailer, $mw);
$adminCtrl        = new AdminController($invoiceService, $projectService, $clientService, $mw, $mailer, $notificationService, $activityLogService);
$notifCtrl        = new NotificationController($notificationService, $mw);
$errorLogCtrl     = new ErrorLogController($errorLogService, $mw);
$deliverableCtrl  = new DeliverableController($deliverableService, $mw);

// Social Metrics
$socialProfileRepo   = new MySQLSocialProfileRepository($pdo);
$socialPostRepo      = new MySQLSocialPostRepository($pdo);
$metricsRepo         = new MySQLMetricsRepository($pdo);
$socialMetricsService = new SocialMetricsService($socialProfileRepo, $socialPostRepo, $metricsRepo);
$socialMetricsCtrl   = new SocialMetricsController($socialMetricsService, $mw);

// Messages
$messageService = new MessageService(new MySQLMessageRepository($pdo));
$messageCtrl    = new MessageController($messageService, $mw, $notificationService);

// Onboarding
$onboardingCtrl = new OnboardingController($pdo, $clientService, $mw, $mailer);

// Monthly Reports
$reportService     = new MonthlyReportService($pdo, $projectService, $deliverableService, $socialMetricsService, $clientService);
$reportPdfRenderer = new ReportPdfRenderer();
$reportCtrl        = new ReportController($reportService, $reportPdfRenderer, $mw);

// ── Rate limiting ─────────────────────────────────────────────────────────────
RateLimitMiddleware::check();

// ── Routing ───────────────────────────────────────────────────────────────────
$router = new Router(function (\FastRoute\RouteCollector $r) use (
    $authCtrl, $clientCtrl, $projectCtrl, $invoiceCtrl, $paymentCtrl, $adminCtrl, $deliverableCtrl, $notifCtrl, $errorLogCtrl, $socialMetricsCtrl, $messageCtrl, $onboardingCtrl, $reportCtrl
) {
    // Auth
    $r->addRoute('POST',  '/api/v1/auth/login',           [$authCtrl, 'login']);
    $r->addRoute('POST',  '/api/v1/auth/logout',          [$authCtrl, 'logout']);
    $r->addRoute('GET',   '/api/v1/auth/me',              [$authCtrl, 'me']);
    $r->addRoute('POST',  '/api/v1/auth/forgot-password', [$authCtrl, 'forgotPassword']);
    $r->addRoute('POST',  '/api/v1/auth/reset-password',  [$authCtrl, 'resetPassword']);

    // Clients
    $r->addRoute('GET',   '/api/v1/clients/me',     [$clientCtrl, 'me']);
    $r->addRoute('GET',   '/api/v1/clients',         [$clientCtrl, 'index']);
    $r->addRoute('PUT',   '/api/v1/clients/{id:\d+}', [$clientCtrl, 'update']);

    // Projects
    $r->addRoute('GET',   '/api/v1/projects',                          [$projectCtrl, 'index']);
    $r->addRoute('GET',   '/api/v1/projects/{id:\d+}',                 [$projectCtrl, 'show']);
    $r->addRoute('PATCH', '/api/v1/projects/{id:\d+}/status',          [$projectCtrl, 'updateStatus']);

    // Calendar
    $r->addRoute('GET',   '/api/v1/calendar/events',                   [$projectCtrl, 'calendarEvents']);

    // Invoices
    $r->addRoute('GET',   '/api/v1/invoices',                          [$invoiceCtrl, 'index']);
    $r->addRoute('GET',   '/api/v1/invoices/pending',                  [$invoiceCtrl, 'pending']);
    $r->addRoute('POST',  '/api/v1/invoices/{id:\d+}/pay',             [$invoiceCtrl, 'pay']);
    $r->addRoute('PATCH', '/api/v1/invoices/{id:\d+}/status',          [$invoiceCtrl, 'updateStatus']);
    $r->addRoute('GET',   '/api/v1/invoices/{id:\d+}/pdf',            [$invoiceCtrl, 'downloadPdf']);

    // Payments (Wompi)
    $r->addRoute('POST', '/api/v1/payments/checkout', [$paymentCtrl, 'checkout']);
    $r->addRoute('POST', '/api/v1/payments/webhook',  [$paymentCtrl, 'webhook']);

    // Admin
    $r->addRoute('GET',  '/api/v1/admin/stats',              [$adminCtrl, 'stats']);
    $r->addRoute('GET',  '/api/v1/admin/clients/{id:\d+}',   [$adminCtrl, 'showClient']);
    $r->addRoute('POST', '/api/v1/admin/clients',            [$adminCtrl, 'createClient']);
    $r->addRoute('POST', '/api/v1/admin/invoices', [$adminCtrl, 'createInvoice']);
    $r->addRoute('POST', '/api/v1/admin/projects', [$adminCtrl, 'createProject']);

    // Notifications
    $r->addRoute('GET',   '/api/v1/notifications/unread-count',     [$notifCtrl, 'unreadCount']);
    $r->addRoute('GET',   '/api/v1/notifications',                  [$notifCtrl, 'index']);
    $r->addRoute('PATCH', '/api/v1/notifications/{id:\d+}/read',    [$notifCtrl, 'markRead']);

    // Project Timeline
    $r->addRoute('GET',   '/api/v1/projects/{id:\d+}/timeline',     [$projectCtrl, 'timeline']);

    // Deliverables
    $r->addRoute('GET',  '/api/v1/deliverables',        [$deliverableCtrl, 'listByProject']);
    $r->addRoute('POST', '/api/v1/admin/deliverables',   [$deliverableCtrl, 'upload']);

    // Error Logs
    $r->addRoute('POST', '/api/v1/errors',              [$errorLogCtrl, 'store']);
    $r->addRoute('GET',  '/api/v1/admin/errors',         [$errorLogCtrl, 'index']);

    // Social Metrics
    $r->addRoute('GET',    '/api/v1/admin/social-profiles',              [$socialMetricsCtrl, 'listProfiles']);
    $r->addRoute('POST',   '/api/v1/admin/social-profiles',              [$socialMetricsCtrl, 'addProfile']);
    $r->addRoute('DELETE', '/api/v1/admin/social-profiles/{id:\d+}',     [$socialMetricsCtrl, 'removeProfile']);
    $r->addRoute('PATCH',  '/api/v1/social-posts/{id:\d+}/proway',       [$socialMetricsCtrl, 'toggleProWay']);
    $r->addRoute('GET',    '/api/v1/social-metrics/dashboard',            [$socialMetricsCtrl, 'clientDashboard']);
    $r->addRoute('GET',    '/api/v1/social-metrics/profiles/{id:\d+}/metrics', [$socialMetricsCtrl, 'profileMetrics']);

    // Messages (Project Chat)
    $r->addRoute('GET',  '/api/v1/projects/{id:\d+}/messages',        [$messageCtrl, 'listMessages']);
    $r->addRoute('POST', '/api/v1/projects/{id:\d+}/messages',        [$messageCtrl, 'send']);
    $r->addRoute('GET',  '/api/v1/projects/{id:\d+}/messages/unread', [$messageCtrl, 'unreadCount']);

    // Onboarding
    $r->addRoute('GET',  '/api/v1/clients/me/profile',              [$onboardingCtrl, 'getProfile']);
    $r->addRoute('PUT',  '/api/v1/clients/me/profile',              [$onboardingCtrl, 'updateProfile']);
    $r->addRoute('POST', '/api/v1/clients/me/onboarding-complete',  [$onboardingCtrl, 'completeOnboarding']);

    // Monthly Reports
    $r->addRoute('GET',  '/api/v1/admin/reports',          [$reportCtrl, 'listReports']);
    $r->addRoute('POST', '/api/v1/admin/reports/generate',  [$reportCtrl, 'generate']);
    $r->addRoute('GET',  '/api/v1/reports/{id:\d+}/pdf',    [$reportCtrl, 'downloadPdf']);
    $r->addRoute('GET',  '/api/v1/clients/me/reports',      [$reportCtrl, 'clientReports']);
});

$request  = new Request();
$dispatch = $router->dispatch($request);

match ($dispatch['status']) {
    404 => Response::error('NOT_FOUND', 'Not found', 404),
    405 => Response::error(
        'METHOD_NOT_ALLOWED',
        'Method not allowed. Allowed: ' . implode(', ', $dispatch['allowed'] ?? []),
        405
    ),
    200 => (function () use ($dispatch, $request) {
        [$controller, $method] = $dispatch['handler'];
        $controller->$method($request, $dispatch['vars']);
    })(),
};
