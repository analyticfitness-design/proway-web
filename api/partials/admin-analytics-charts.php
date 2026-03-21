<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

if ($currentUser->type !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert--error">Acceso denegado. Se requieren permisos de administrador.</div>';
    exit;
}

use ProWay\Domain\Analytics\AnalyticsService;
use ProWay\Domain\Analytics\MySQLAnalyticsRepository;

try {
    $analyticsService = new AnalyticsService(new MySQLAnalyticsRepository($pdo));

    $revenueByMonth = $analyticsService->getRevenueByMonth(12);
    $projections    = $analyticsService->getProjections(3);
    $topClients     = $analyticsService->getTopClients(5);
    $overdue        = $analyticsService->getOverdueInvoices();
    $atRisk         = $analyticsService->getClientsAtRisk();
    $clientsByPlan  = $clientService->countByPlan();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los gráficos de analytics. Inténtalo de nuevo.</div>';
    exit;
}

// Invoice status counts for bar chart
try {
    $statusStmt = $pdo->query(
        "SELECT status, COUNT(*) AS total FROM invoices GROUP BY status ORDER BY total DESC"
    );
    $invoicesByStatus = $statusStmt->fetchAll();
} catch (Throwable $e) {
    $invoicesByStatus = [];
}

// Build JSON for Chart.js
$revLabels = array_map(fn($r) => $r['month'], $revenueByMonth);
$revValues = array_map(fn($r) => (float) $r['total'], $revenueByMonth);

$projLabels = array_map(fn($r) => $r['month'], $projections);
$projValues = array_map(fn($r) => (float) $r['projected'], $projections);

// Combine revenue + projection for the line chart
$allLabels = array_merge($revLabels, $projLabels);
$revDataFull = array_merge($revValues, array_fill(0, count($projValues), null));
$projDataFull = array_merge(
    array_fill(0, max(0, count($revValues) - 1), null),
    count($revValues) > 0 ? [end($revValues)] : [],
    $projValues
);

$planLabels = array_map(fn($r) => $r['plan_type'], $clientsByPlan);
$planValues = array_map(fn($r) => (int) $r['total'], $clientsByPlan);

$statusLabels = array_map(fn($r) => $r['status'], $invoicesByStatus);
$statusValues = array_map(fn($r) => (int) $r['total'], $invoicesByStatus);

$allLabelsJson   = json_encode($allLabels);
$revDataJson     = json_encode($revDataFull);
$projDataJson    = json_encode($projDataFull);
$planLabelsJson  = json_encode($planLabels);
$planValuesJson  = json_encode($planValues);
$statusLabelsJson = json_encode($statusLabels);
$statusValuesJson = json_encode($statusValues);
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--pw-space-4); margin-bottom: var(--pw-space-4);">

    <!-- Revenue + Projections line chart -->
    <div class="card" style="padding: var(--pw-space-4);">
        <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">Revenue Últimos 12 Meses + Proyección</h3>
        <canvas id="analyticsRevenueChart" height="300" style="max-height: 300px;"></canvas>
    </div>

    <!-- Plan mix doughnut -->
    <div class="card" style="padding: var(--pw-space-4);">
        <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">Mix de Planes</h3>
        <canvas id="analyticsPlanChart" height="300" style="max-height: 300px;"></canvas>
    </div>

</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--pw-space-4); margin-bottom: var(--pw-space-4);">

    <!-- Invoices by status bar chart -->
    <div class="card" style="padding: var(--pw-space-4);">
        <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">Facturas por Estado</h3>
        <canvas id="analyticsStatusChart" height="300" style="max-height: 300px;"></canvas>
    </div>

    <!-- Top clients + overdue table -->
    <div class="card" style="padding: var(--pw-space-4);">
        <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">Top 5 Clientes por Revenue</h3>
        <div class="table-wrapper">
            <table class="table" style="font-size: 0.8rem;">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Plan</th>
                        <th>Revenue Total</th>
                        <th>Facturas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topClients)): ?>
                    <tr><td colspan="4" style="text-align:center; color: var(--pw-text-muted);">Sin datos</td></tr>
                    <?php else: ?>
                    <?php foreach ($topClients as $client): ?>
                    <tr>
                        <td><?= htmlspecialchars($client['name']) ?></td>
                        <td><span class="badge"><?= htmlspecialchars($client['plan_type']) ?></span></td>
                        <td>$<?= number_format((float) $client['total_revenue'], 0, ',', '.') ?></td>
                        <td><?= (int) $client['invoice_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($overdue)): ?>
        <h4 class="card__title" style="margin-top: var(--pw-space-3); margin-bottom: var(--pw-space-2); color: #E31E24;">
            Facturas Vencidas (<?= count($overdue) ?>)
        </h4>
        <div class="table-wrapper">
            <table class="table" style="font-size: 0.8rem;">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Cliente</th>
                        <th>Monto</th>
                        <th>Días Vencida</th>
                        <th>Riesgo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdue as $inv): ?>
                    <?php
                        $daysOver = (int) ($inv['days_overdue'] ?? 0);
                        if ($daysOver > 30) {
                            $riskLevel = 'Alto';
                            $riskColor = '#E31E24';
                        } elseif ($daysOver > 15) {
                            $riskLevel = 'Medio';
                            $riskColor = '#FBBF24';
                        } else {
                            $riskLevel = 'Bajo';
                            $riskColor = '#00FF87';
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($inv['invoice_number'] ?? '#' . $inv['id']) ?></td>
                        <td><?= htmlspecialchars($inv['client_name'] ?? 'N/A') ?></td>
                        <td>$<?= number_format((float) $inv['total_cop'], 0, ',', '.') ?></td>
                        <td><?= $daysOver ?> días</td>
                        <td><span style="color: <?= $riskColor ?>; font-weight: 600;"><?= $riskLevel ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
/* Chart.js is preloaded in the analytics template */
if (typeof Chart === 'undefined') {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
    s.onload = function() { initAnalyticsCharts(); };
    document.head.appendChild(s);
} else {
    initAnalyticsCharts();
}
function initAnalyticsCharts() {
(function() {
    const CYAN   = '#00D9FF';
    const GREEN  = '#00FF87';
    const RED    = '#E31E24';
    const YELLOW = '#FBBF24';
    const PURPLE = '#8B5CF6';
    const ORANGE = '#F97316';
    const GRAY   = '#A1A1AA';
    const DARK_GRID = 'rgba(161,161,170,0.15)';

    // ── Revenue + Projections Line Chart ──────────────────────────
    const revCtx = document.getElementById('analyticsRevenueChart');
    if (revCtx) {
        new Chart(revCtx, {
            type: 'line',
            data: {
                labels: <?= $allLabelsJson ?>,
                datasets: [
                    {
                        label: 'Revenue Real (COP)',
                        data: <?= $revDataJson ?>,
                        borderColor: CYAN,
                        backgroundColor: 'rgba(0, 217, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: CYAN,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        spanGaps: false,
                    },
                    {
                        label: 'Proyección (COP)',
                        data: <?= $projDataJson ?>,
                        borderColor: GREEN,
                        backgroundColor: 'rgba(0, 255, 135, 0.05)',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: GREEN,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        spanGaps: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: GRAY }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.parsed.y === null) return '';
                                return context.dataset.label + ': $' + new Intl.NumberFormat('es-CO').format(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: GRAY },
                        grid: { color: DARK_GRID }
                    },
                    y: {
                        ticks: {
                            color: GRAY,
                            callback: function(value) {
                                return '$' + new Intl.NumberFormat('es-CO').format(value);
                            }
                        },
                        grid: { color: DARK_GRID }
                    }
                }
            }
        });
    }

    // ── Plan Mix Doughnut ─────────────────────────────────────────
    const planColors = {
        starter:    CYAN,
        growth:     GREEN,
        authority:  PURPLE,
        proyectos:  ORANGE,
    };
    const planLabels = <?= $planLabelsJson ?>;
    const planData   = <?= $planValuesJson ?>;
    const planBgColors = planLabels.map(p => planColors[p] || GRAY);

    const planCtx = document.getElementById('analyticsPlanChart');
    if (planCtx) {
        new Chart(planCtx, {
            type: 'doughnut',
            data: {
                labels: planLabels,
                datasets: [{
                    data: planData,
                    backgroundColor: planBgColors,
                    borderColor: 'rgba(0,0,0,0.3)',
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: GRAY, padding: 12 }
                    }
                },
                cutout: '60%',
            }
        });
    }

    // ── Invoices by Status Horizontal Bar ─────────────────────────
    const invStatusColors = {
        pagada:     GREEN,
        pendiente:  YELLOW,
        enviada:    CYAN,
        vencida:    RED,
        borrador:   GRAY,
        cancelada:  '#6B7280',
    };
    const invStatusLabels = <?= $statusLabelsJson ?>;
    const invStatusData   = <?= $statusValuesJson ?>;
    const invBgColors = invStatusLabels.map(s => invStatusColors[s] || GRAY);

    const statusCtx = document.getElementById('analyticsStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: invStatusLabels,
                datasets: [{
                    label: 'Facturas',
                    data: invStatusData,
                    backgroundColor: invBgColors,
                    borderColor: 'rgba(0,0,0,0.2)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
                        ticks: { color: GRAY, stepSize: 1 },
                        grid: { color: DARK_GRID }
                    },
                    y: {
                        ticks: { color: GRAY },
                        grid: { display: false }
                    }
                }
            }
        });
    }

})();
}
</script>
