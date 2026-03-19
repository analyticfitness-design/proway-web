<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

const PROJECT_STATUS_MAP = [
    'cotizacion'   => ['class' => 'badge--neutral',   'label' => 'Cotización'],
    'confirmado'   => ['class' => 'badge--enviada',   'label' => 'Confirmado'],
    'en_produccion'=> ['class' => 'badge--pendiente', 'label' => 'En Producción'],
    'revision'     => ['class' => 'badge--pendiente', 'label' => 'Revisión'],
    'entregado'    => ['class' => 'badge--pagada',    'label' => 'Entregado'],
    'facturado'    => ['class' => 'badge--pagada',    'label' => 'Facturado'],
    'pagado'       => ['class' => 'badge--pagada',    'label' => 'Pagado'],
];

try {
    $projects = $projectService->listForClient($currentUser->id);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los proyectos. Inténtalo de nuevo.</div>';
    exit;
}

if (empty($projects)) {
    echo '<p class="text-muted" style="padding: var(--pw-space-4);">No tienes proyectos activos en este momento.</p>';
    exit;
}

foreach ($projects as $project):
    $status     = $project['status'] ?? 'cotizacion';
    $statusInfo = PROJECT_STATUS_MAP[$status] ?? ['class' => 'badge--neutral', 'label' => ucfirst($status)];
    $title      = htmlspecialchars($project['title'] ?? $project['service_type'] ?? 'Sin título', ENT_QUOTES, 'UTF-8');
    $type       = htmlspecialchars($project['service_type'] ?? '', ENT_QUOTES, 'UTF-8');
    $deadline   = !empty($project['deadline']) ? date('d/m/Y', strtotime($project['deadline'])) : '—';
?>
<div class="project-card">
    <div class="project-card__header">
        <h3 class="project-card__title"><?= $title ?></h3>
        <span class="badge <?= htmlspecialchars($statusInfo['class'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>
    <?php if ($type): ?>
    <p style="font-size:0.8rem; color:var(--pw-text-muted); margin: var(--pw-space-1) 0;"><?= $type ?></p>
    <?php endif; ?>
    <div class="project-card__footer">
        <span class="project-card__date">Entrega: <?= $deadline ?></span>
    </div>
</div>
<?php endforeach; ?>
