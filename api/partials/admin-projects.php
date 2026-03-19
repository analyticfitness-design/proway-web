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

const PROJECT_STATUS_MAP = [
    'pendiente'  => ['class' => 'badge--neutral',   'label' => 'Pendiente'],
    'produccion' => ['class' => 'badge--enviada',    'label' => 'Producción'],
    'revision'   => ['class' => 'badge--pendiente',  'label' => 'Revisión'],
    'entrega'    => ['class' => 'badge--pagada',     'label' => 'Entrega'],
    'completado' => ['class' => 'badge--pagada',     'label' => 'Completado'],
    'cancelado'  => ['class' => 'badge--vencido',    'label' => 'Cancelado'],
];

try {
    $projects = $projectService->listAll();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los proyectos. Inténtalo de nuevo.</div>';
    exit;
}

if (empty($projects)) {
    echo '<p class="text-muted" style="padding: var(--pw-space-4);">No hay proyectos registrados.</p>';
    exit;
}
?>
<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Proyecto</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $proj):
                $status     = $proj['status'] ?? 'pendiente';
                $statusInfo = PROJECT_STATUS_MAP[$status] ?? ['class' => 'badge--neutral', 'label' => ucfirst($status)];
                $client     = htmlspecialchars($proj['client_name'] ?? $proj['client_code'] ?? '—', ENT_QUOTES, 'UTF-8');
                $name       = htmlspecialchars($proj['name']        ?? '—', ENT_QUOTES, 'UTF-8');
                $type       = htmlspecialchars(ucfirst($proj['type'] ?? '—'), ENT_QUOTES, 'UTF-8');
                $date       = isset($proj['created_at']) ? date('d/m/Y', strtotime($proj['created_at'])) : '—';
            ?>
            <tr>
                <td><?= $client ?></td>
                <td><?= $name ?></td>
                <td><?= $type ?></td>
                <td>
                    <span class="badge <?= htmlspecialchars($statusInfo['class'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td><?= $date ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
