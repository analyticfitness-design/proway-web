<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

if ($currentUser->type !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert--error">Acceso denegado.</div>';
    exit;
}

use ProWay\Domain\Report\MonthlyReportService;
use ProWay\Domain\Report\ReportPdfRenderer;
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
    $clients = $clientService->getActiveClients();
    $reports = $reportService->listAll();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los reportes.</div>';
    exit;
}

// Index reports by client+period for quick lookup
$reportIndex = [];
foreach ($reports as $r) {
    $key = $r['client_id'] . '-' . $r['year'] . '-' . $r['month'];
    $reportIndex[$key] = $r;
}

$currentYear  = (int) date('Y');
$currentMonth = (int) date('n');
?>

<!-- Generate Report Form -->
<div class="card" style="margin-bottom: var(--pw-space-4);">
    <div class="card__header">
        <h3 class="card__title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -3px;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
            Generar Reporte Mensual
        </h3>
    </div>
    <form id="generate-report-form"
          hx-post="/api/v1/admin/reports/generate"
          hx-target="#report-result"
          hx-swap="innerHTML"
          hx-indicator="#report-spinner"
          style="padding: var(--pw-space-4); display: flex; flex-direction: column; gap: var(--pw-space-3);">

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--pw-space-3);">
            <div>
                <label class="form-label" for="rpt-client">Cliente</label>
                <select name="client_id" id="rpt-client" class="form-input" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($clients as $c): ?>
                    <option value="<?= (int) $c['id'] ?>">
                        <?= htmlspecialchars($c['nombre'] ?? $c['name'] ?? $c['email'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="rpt-year">A&ntilde;o</label>
                <select name="year" id="rpt-year" class="form-input" required>
                    <?php for ($y = $currentYear; $y >= $currentYear - 2; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="rpt-month">Mes</label>
                <select name="month" id="rpt-month" class="form-input" required>
                    <?php foreach ($monthNames as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $num === $currentMonth - 1 || ($currentMonth === 1 && $num === 12) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="form-label" for="rpt-recs">Recomendaciones (opcional)</label>
            <textarea name="recommendations" id="rpt-recs" class="form-input" rows="3"
                      placeholder="Recomendaciones del equipo ProWay para el cliente..."></textarea>
        </div>

        <div style="display: flex; align-items: center; gap: var(--pw-space-2);">
            <button type="submit" class="btn btn--primary btn--sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                Generar Reporte
            </button>
            <span id="report-spinner" class="htmx-indicator" style="color: var(--pw-accent);">Generando...</span>
        </div>

        <div id="report-result"></div>
    </form>
</div>

<!-- Existing Reports -->
<div class="card">
    <div class="card__header">
        <h3 class="card__title">Reportes Generados</h3>
    </div>

    <?php if (empty($reports)): ?>
        <p style="padding: var(--pw-space-4); color: var(--pw-text-muted);">
            No hay reportes generados a&uacute;n.
        </p>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Per&iacute;odo</th>
                    <th>Recomendaciones</th>
                    <th>Generado</th>
                    <th>PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r):
                    $rClientName = htmlspecialchars($r['client_name'] ?? $r['client_email'] ?? '—', ENT_QUOTES, 'UTF-8');
                    $rMonth      = (int) ($r['month'] ?? 0);
                    $rYear       = (int) ($r['year'] ?? 0);
                    $rPeriod     = ($monthNames[$rMonth] ?? '') . ' ' . $rYear;
                    $rRecs       = $r['recommendations'] ?? '';
                    $rRecsShort  = mb_strlen($rRecs) > 60 ? mb_substr($rRecs, 0, 60) . '...' : $rRecs;
                    $rGenerated  = !empty($r['generated_at'])
                        ? date('d/m/Y H:i', strtotime($r['generated_at']))
                        : '—';
                    $rId = (int) ($r['id'] ?? 0);
                ?>
                <tr>
                    <td><?= $rClientName ?></td>
                    <td><strong><?= htmlspecialchars($rPeriod, ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td style="color: var(--pw-text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= htmlspecialchars($rRecsShort, ENT_QUOTES, 'UTF-8') ?: '<em>—</em>' ?>
                    </td>
                    <td style="white-space: nowrap;"><?= $rGenerated ?></td>
                    <td>
                        <?php if ($rId > 0): ?>
                        <a href="/api/v1/reports/<?= $rId ?>/pdf"
                           target="_blank"
                           class="btn btn--ghost btn--sm"
                           title="Ver Reporte PDF">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            Ver PDF
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
