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

    $mrr          = $analyticsService->getMRR();
    $mrrNeto      = $analyticsService->getMRRNeto();
    $revMonth     = $analyticsService->getRevenueThisMonth();
    $churnRate    = $analyticsService->getChurnRate();
    $ltv          = $analyticsService->getLTV();
    $arpu         = $analyticsService->getARPU();
    $overdue      = $analyticsService->getOverdueInvoices();
    $atRisk       = $analyticsService->getClientsAtRisk();
    $overdueCount = count($overdue);
    $riskCount    = count($atRisk);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar las métricas financieras. Inténtalo de nuevo.</div>';
    exit;
}

// Churn color thresholds
if ($churnRate < 3) {
    $churnColor = '#00FF87'; // green
    $churnBg    = 'rgba(0, 255, 135, 0.1)';
} elseif ($churnRate < 8) {
    $churnColor = '#FBBF24'; // yellow
    $churnBg    = 'rgba(251, 191, 36, 0.1)';
} else {
    $churnColor = '#E31E24'; // red
    $churnBg    = 'rgba(227, 30, 36, 0.1)';
}

$fmtMRR     = '$' . number_format($mrrNeto, 0, ',', '.');
$fmtRevenue = '$' . number_format($revMonth, 0, ',', '.');
$fmtLTV     = '$' . number_format($ltv, 0, ',', '.');
$fmtARPU    = '$' . number_format($arpu, 0, ',', '.');
?>

<!-- KPI Row 1: Main financial metrics -->
<div class="stat-card">
    <div class="stat-card__value" style="color: var(--pw-accent);"><?= $fmtMRR ?></div>
    <div class="stat-card__label">MRR Neto</div>
    <div style="font-size: 0.7rem; color: var(--pw-text-muted); margin-top: 2px;">
        Bruto: $<?= number_format($mrr, 0, ',', '.') ?>
    </div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= $fmtRevenue ?></div>
    <div class="stat-card__label">Revenue Total Mes</div>
</div>

<div class="stat-card" style="border-color: <?= $churnColor ?>33;">
    <div class="stat-card__value" style="color: <?= $churnColor ?>;"><?= number_format($churnRate, 1) ?>%</div>
    <div class="stat-card__label">Churn Rate</div>
    <div style="font-size: 0.65rem; color: <?= $churnColor ?>; margin-top: 2px;">
        <?= $churnRate < 3 ? 'Saludable' : ($churnRate < 8 ? 'Atención' : 'Crítico') ?>
    </div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= $fmtLTV ?></div>
    <div class="stat-card__label">LTV Promedio</div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= $fmtARPU ?></div>
    <div class="stat-card__label">ARPU</div>
</div>

<div class="stat-card" <?= $overdueCount > 0 ? 'style="border-color: rgba(227,30,36,0.3);"' : '' ?>>
    <div class="stat-card__value" style="color: <?= $overdueCount > 0 ? '#E31E24' : 'var(--pw-text)' ?>;"><?= $overdueCount ?></div>
    <div class="stat-card__label">Facturas Vencidas</div>
</div>

<div class="stat-card" <?= $riskCount > 0 ? 'style="border-color: rgba(251,191,36,0.3);"' : '' ?>>
    <div class="stat-card__value" style="color: <?= $riskCount > 0 ? '#FBBF24' : 'var(--pw-text)' ?>;"><?= $riskCount ?></div>
    <div class="stat-card__label">Clientes en Riesgo</div>
</div>
