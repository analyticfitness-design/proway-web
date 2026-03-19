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
    $activeClients = $clientService->getActiveClients();
    $clientCount   = count($activeClients);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar las estadísticas. Inténtalo de nuevo.</div>';
    exit;
}
?>
<div class="stat-card">
    <div class="stat-card__value"><?= $clientCount ?></div>
    <div class="stat-card__label">Clientes Activos</div>
</div>

<div class="stat-card">
    <div class="stat-card__value">—</div>
    <div class="stat-card__label">Proyectos Activos</div>
</div>

<div class="stat-card">
    <div class="stat-card__value">—</div>
    <div class="stat-card__label">Facturas Pendientes</div>
</div>

<div class="stat-card">
    <div class="stat-card__value">—</div>
    <div class="stat-card__label">Ingresos del Mes</div>
</div>
