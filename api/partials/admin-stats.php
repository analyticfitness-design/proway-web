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
    $activeClients  = $clientService->getActiveClients();
    $clientCount    = count($activeClients);
    $activeProjects = $projectService->countActive();
    $pendingInv     = $invoiceService->countPending();
    $monthlyIncome  = $invoiceService->sumPaidThisMonth();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar las estadísticas. Inténtalo de nuevo.</div>';
    exit;
}

$incomeFormatted = '$' . number_format($monthlyIncome, 0, ',', '.');
?>
<div class="stat-card">
    <div class="stat-card__value"><?= $clientCount ?></div>
    <div class="stat-card__label">Clientes Activos</div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= $activeProjects ?></div>
    <div class="stat-card__label">Proyectos Activos</div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= $pendingInv ?></div>
    <div class="stat-card__label">Facturas Pendientes</div>
</div>

<div class="stat-card">
    <div class="stat-card__value"><?= $incomeFormatted ?></div>
    <div class="stat-card__label">Ingresos del Mes</div>
</div>
