<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $projects        = $projectService->listForClient($currentUser->id);
    $activeProjects  = array_filter($projects, fn(array $p) => ($p['status'] ?? '') !== 'completado');
    $pendingInvoices = $invoiceService->getPendingForClient($currentUser->id);

    $activeCount  = count($activeProjects);
    $pendingCount = count($pendingInvoices);
    $plan         = $currentUser->planType !== '' ? ucfirst($currentUser->planType) : 'Sin plan';
    $code         = $currentUser->code !== '' ? $currentUser->code : '—';

} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar estadísticas. Inténtalo de nuevo.</div>';
    exit;
}
?>
<div class="stat-card">
    <div class="stat-card__value"><?= $activeCount ?></div>
    <div class="stat-card__label">Proyectos Activos</div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= $pendingCount ?></div>
    <div class="stat-card__label">Facturas Pendientes</div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= htmlspecialchars($plan, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="stat-card__label">Plan Actual</div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="stat-card__label">Código Cliente</div>
</div>
