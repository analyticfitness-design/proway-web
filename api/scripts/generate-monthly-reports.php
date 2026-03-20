<?php
declare(strict_types=1);

/**
 * Cron: Generate monthly progress reports for all active clients.
 *
 * Usage:  php /code/api/scripts/generate-monthly-reports.php
 * Cron:   0 8 1 * * php /code/api/scripts/generate-monthly-reports.php >> /var/log/monthly-reports.log 2>&1
 *
 * Runs on the 1st of each month, generating reports for the previous month.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ProWay\Domain\Client\ClientService;
use ProWay\Domain\Client\MySQLClientRepository;
use ProWay\Domain\Deliverable\DeliverableService;
use ProWay\Domain\Deliverable\MySQLDeliverableRepository;
use ProWay\Domain\Project\ProjectService;
use ProWay\Domain\Project\MySQLProjectRepository;
use ProWay\Domain\Report\MonthlyReportService;
use ProWay\Domain\Report\ReportPdfRenderer;
use ProWay\Domain\SocialMetrics\MySQLSocialProfileRepository;
use ProWay\Domain\SocialMetrics\MySQLSocialPostRepository;
use ProWay\Domain\SocialMetrics\MySQLMetricsRepository;
use ProWay\Domain\SocialMetrics\SocialMetricsService;
use ProWay\Infrastructure\Database\Connection;

// ── Bootstrap ────────────────────────────────────────────────────────────────
$pdo = Connection::getInstance();

$clientService  = new ClientService(new MySQLClientRepository($pdo));
$projectService = new ProjectService(new MySQLProjectRepository($pdo));
$deliverableService = new DeliverableService(new MySQLDeliverableRepository($pdo));
$socialMetricsService = new SocialMetricsService(
    new MySQLSocialProfileRepository($pdo),
    new MySQLSocialPostRepository($pdo),
    new MySQLMetricsRepository($pdo),
);

$reportService = new MonthlyReportService(
    $pdo,
    $projectService,
    $deliverableService,
    $socialMetricsService,
    $clientService,
);
$renderer = new ReportPdfRenderer();

// ── Determine report period (previous month) ────────────────────────────────
$prevMonth = new \DateTimeImmutable('first day of last month');
$year  = (int) $prevMonth->format('Y');
$month = (int) $prevMonth->format('n');

$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
$periodLabel = ($monthNames[$month] ?? '') . " $year";

echo sprintf("[%s] Generando reportes mensuales — %s\n", date('Y-m-d H:i:s'), $periodLabel);

// ── Ensure output directory ──────────────────────────────────────────────────
$reportsDir = __DIR__ . '/../data/reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0755, true);
}

// ── Generate for all active clients ──────────────────────────────────────────
$clients = $clientService->getActiveClients();
$total   = count($clients);
$success = 0;
$errors  = 0;

echo sprintf("  Clientes activos: %d\n", $total);

foreach ($clients as $client) {
    $clientId   = (int) $client['id'];
    $clientName = $client['nombre'] ?? $client['name'] ?? 'Cliente';

    echo sprintf("  [%d] %s ... ", $clientId, $clientName);

    try {
        // Gather data
        $reportData = $reportService->generateForClient($clientId, $year, $month);
        $reportData['recommendations'] = '';

        // Render HTML
        $html = $renderer->render($reportData);

        // Save file
        $filename = sprintf('report_%d_%04d_%02d.html', $clientId, $year, $month);
        $filePath = $reportsDir . '/' . $filename;
        file_put_contents($filePath, $html);

        $pdfPath = '/data/reports/' . $filename;

        // Save DB record
        $reportService->save($clientId, $year, $month, null, $pdfPath);

        $success++;
        echo "OK\n";

    } catch (\Throwable $e) {
        $errors++;
        echo sprintf("ERROR: %s\n", $e->getMessage());
        error_log(sprintf(
            '[monthly-reports] Failed for client %d (%s): %s',
            $clientId, $clientName, $e->getMessage()
        ));
    }
}

echo sprintf(
    "[%s] Completado — %d/%d exitosos, %d errores\n",
    date('Y-m-d H:i:s'),
    $success,
    $total,
    $errors
);

exit($errors > 0 ? 1 : 0);
