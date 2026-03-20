<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

use ProWay\Domain\Report\MonthlyReportService;
use ProWay\Domain\Deliverable\DeliverableService;
use ProWay\Domain\Deliverable\MySQLDeliverableRepository;

$deliverableServiceLocal = new DeliverableService(new MySQLDeliverableRepository($pdo));
$reportService = new MonthlyReportService(
    $pdo,
    $projectService,
    $deliverableServiceLocal,
    $socialMetricsService,
    $clientService,
);

$monthNames = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];

try {
    $reports = $reportService->listForClient($currentUser->id);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los reportes.</div>';
    exit;
}
?>

<?php if (empty($reports)): ?>
    <div style="padding: var(--pw-space-6); text-align: center;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--pw-text-muted)" stroke-width="1.5"
             style="margin-bottom: var(--pw-space-3); opacity: .5;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        <p style="color: var(--pw-text-muted); font-size: 15px;">
            A&uacute;n no tienes reportes mensuales disponibles.
        </p>
        <p style="color: var(--pw-text-muted); font-size: 13px; margin-top: var(--pw-space-1);">
            Los reportes de avance se generan al inicio de cada mes.
        </p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--pw-space-4); padding: var(--pw-space-4);">
        <?php foreach ($reports as $r):
            $rMonth  = (int) ($r['month'] ?? 0);
            $rYear   = (int) ($r['year'] ?? 0);
            $period  = ($monthNames[$rMonth] ?? '') . ' ' . $rYear;
            $genDate = !empty($r['generated_at'])
                ? date('d/m/Y', strtotime($r['generated_at']))
                : '—';
            $hasRecs = !empty($r['recommendations']);
            $rId     = (int) ($r['id'] ?? 0);
        ?>
        <div class="card" style="overflow: hidden;">
            <div style="padding: var(--pw-space-4);">
                <!-- Period heading -->
                <div style="display: flex; align-items: center; gap: var(--pw-space-2); margin-bottom: var(--pw-space-3);">
                    <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, rgba(6,182,212,.15), rgba(6,182,212,.05)); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--pw-accent)" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                    <div>
                        <h4 style="font-size: 15px; font-weight: 600; color: var(--pw-text-primary);">
                            <?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?>
                        </h4>
                        <p style="font-size: 12px; color: var(--pw-text-muted);">
                            Generado: <?= $genDate ?>
                        </p>
                    </div>
                </div>

                <!-- Summary badges -->
                <div style="display: flex; gap: var(--pw-space-2); flex-wrap: wrap; margin-bottom: var(--pw-space-3);">
                    <?php if ($hasRecs): ?>
                    <span class="badge badge--enviada" style="font-size: 11px;">
                        Con recomendaciones
                    </span>
                    <?php endif; ?>
                    <span class="badge badge--pagada" style="font-size: 11px;">
                        Reporte completo
                    </span>
                </div>

                <!-- Download button -->
                <?php if ($rId > 0): ?>
                <a href="/api/v1/reports/<?= $rId ?>/pdf"
                   target="_blank"
                   class="btn btn--primary btn--sm"
                   style="width: 100%; justify-content: center;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Ver Reporte
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
