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

try {
    $revenueByMonth   = $invoiceService->revenueByMonth(6);
    $projectsByStatus = $projectService->countByStatus();
    $clientsByPlan    = $clientService->countByPlan();
    $newClientsByMonth = $clientService->newByMonth(6);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los gráficos. Inténtalo de nuevo.</div>';
    exit;
}

// Build JSON data for Chart.js
$revenueLabels = array_map(fn($r) => $r['month'], $revenueByMonth);
$revenueValues = array_map(fn($r) => (float) $r['total'], $revenueByMonth);

$statusLabels = array_map(fn($r) => $r['status'], $projectsByStatus);
$statusValues = array_map(fn($r) => (int) $r['total'], $projectsByStatus);

$revenueLabelsJson = json_encode($revenueLabels);
$revenueValuesJson = json_encode($revenueValues);
$statusLabelsJson  = json_encode($statusLabels);
$statusValuesJson  = json_encode($statusValues);
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--pw-space-4);">

    <!-- Revenue line chart -->
    <div class="card" style="padding: var(--pw-space-4);">
        <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">Ingresos Últimos 6 Meses</h3>
        <canvas id="revenueChart" height="300" style="max-height: 300px;"></canvas>
    </div>

    <!-- Projects by status doughnut -->
    <div class="card" style="padding: var(--pw-space-4);">
        <h3 class="card__title" style="margin-bottom: var(--pw-space-3);">Proyectos por Estado</h3>
        <canvas id="projectsChart" height="300" style="max-height: 300px;"></canvas>
    </div>

</div>

<script>
/* Chart.js is preloaded in the admin dashboard template */
if (typeof Chart === 'undefined') {
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
    s.onload = function() { initCharts(); };
    document.head.appendChild(s);
} else {
    initCharts();
}
function initCharts() {
(function() {
    const CYAN  = '#00D9FF';
    const GREEN = '#00FF87';
    const RED   = '#E31E24';
    const GRAY  = '#A1A1AA';
    const DARK_GRID = 'rgba(161,161,170,0.15)';

    const darkDefaults = {
        color: GRAY,
        borderColor: DARK_GRID,
    };

    // ── Revenue Line Chart ───────────────────────────────────
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?= $revenueLabelsJson ?>,
                datasets: [{
                    label: 'Ingresos (COP)',
                    data: <?= $revenueValuesJson ?>,
                    borderColor: CYAN,
                    backgroundColor: 'rgba(0, 217, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: CYAN,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
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
                                return '$' + new Intl.NumberFormat('es-CO').format(context.parsed.y);
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

    // ── Projects Doughnut Chart ──────────────────────────────
    const statusColors = {
        cotizacion:     GRAY,
        confirmado:     CYAN,
        en_produccion:  GREEN,
        revision:       '#FBBF24',
        entregado:      '#8B5CF6',
        facturado:      '#F97316',
        pagado:         '#10B981',
    };

    const statusLabels = <?= $statusLabelsJson ?>;
    const statusData   = <?= $statusValuesJson ?>;
    const bgColors = statusLabels.map(s => statusColors[s] || GRAY);

    const projectsCtx = document.getElementById('projectsChart');
    if (projectsCtx) {
        new Chart(projectsCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: bgColors,
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
}
</script>
