<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use ProWay\Api\V1\Controller\AdminController;
use ProWay\Api\V1\Controller\AuthController;
use ProWay\Api\V1\Controller\ClientController;
use ProWay\Api\V1\Controller\InvoiceController;
use ProWay\Api\V1\Controller\PaymentController;
use ProWay\Api\V1\Controller\ProjectController;
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
use ProWay\Domain\Payment\WompiService;
use ProWay\Infrastructure\Cache\CacheFactory;
use ProWay\Infrastructure\Database\Connection;
use ProWay\Infrastructure\Email\MailjetService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;
use ProWay\Infrastructure\Http\Router;

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

$wompi   = new WompiService();
$mailer  = new MailjetService();

$authCtrl    = new AuthController($auth, $mw);
$clientCtrl  = new ClientController($clientService, $mw);
$projectCtrl = new ProjectController($projectService, $mw);
$invoiceCtrl = new InvoiceController($invoiceService, $mw);
$paymentCtrl = new PaymentController($invoiceService, $clientService, $wompi, $mailer, $mw);
$adminCtrl   = new AdminController($invoiceService, $projectService, $clientService, $mw);

// ── Rate limiting ─────────────────────────────────────────────────────────────
RateLimitMiddleware::check();

// ── Routing ───────────────────────────────────────────────────────────────────
$router = new Router(function (\FastRoute\RouteCollector $r) use (
    $authCtrl, $clientCtrl, $projectCtrl, $invoiceCtrl, $paymentCtrl, $adminCtrl
) {
    // Auth
    $r->addRoute('POST',  '/api/v1/auth/login',  [$authCtrl, 'login']);
    $r->addRoute('POST',  '/api/v1/auth/logout', [$authCtrl, 'logout']);
    $r->addRoute('GET',   '/api/v1/auth/me',     [$authCtrl, 'me']);

    // Clients
    $r->addRoute('GET',   '/api/v1/clients/me',     [$clientCtrl, 'me']);
    $r->addRoute('GET',   '/api/v1/clients',         [$clientCtrl, 'index']);
    $r->addRoute('PUT',   '/api/v1/clients/{id:\d+}', [$clientCtrl, 'update']);

    // Projects
    $r->addRoute('GET',   '/api/v1/projects',                          [$projectCtrl, 'index']);
    $r->addRoute('GET',   '/api/v1/projects/{id:\d+}',                 [$projectCtrl, 'show']);
    $r->addRoute('PATCH', '/api/v1/projects/{id:\d+}/status',          [$projectCtrl, 'updateStatus']);

    // Invoices
    $r->addRoute('GET',   '/api/v1/invoices',                          [$invoiceCtrl, 'index']);
    $r->addRoute('GET',   '/api/v1/invoices/pending',                  [$invoiceCtrl, 'pending']);
    $r->addRoute('POST',  '/api/v1/invoices/{id:\d+}/pay',             [$invoiceCtrl, 'pay']);
    $r->addRoute('PATCH', '/api/v1/invoices/{id:\d+}/status',          [$invoiceCtrl, 'updateStatus']);

    // Payments (Wompi)
    $r->addRoute('POST', '/api/v1/payments/checkout', [$paymentCtrl, 'checkout']);
    $r->addRoute('POST', '/api/v1/payments/webhook',  [$paymentCtrl, 'webhook']);

    // Admin
    $r->addRoute('GET',  '/api/v1/admin/stats',    [$adminCtrl, 'stats']);
    $r->addRoute('POST', '/api/v1/admin/clients',  [$adminCtrl, 'createClient']);
    $r->addRoute('POST', '/api/v1/admin/invoices', [$adminCtrl, 'createInvoice']);
    $r->addRoute('POST', '/api/v1/admin/projects', [$adminCtrl, 'createProject']);
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
