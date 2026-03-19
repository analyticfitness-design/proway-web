<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

// Map DB status values to CSS modifier classes and display labels.
const PROJECT_STATUS_MAP = [
    'pendiente'   => ['class' => 'badge--pendiente',  'label' => 'Pendiente'],
    'en_progreso' => ['class' => 'badge--en-progreso', 'label' => 'En Progreso'],
    'revision'    => ['class' => 'badge--revision',    'label' => 'Revisión'],
    'completado'  => ['class' => 'badge--completado',  'label' => 'Completado'],
];

try {
    $projects = $projectService->listForClient($currentUser->id);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los proyectos. Inténtalo de nuevo.</div>';
    exit;
}

if (empty($projects)) {
    echo '<p class="text-muted">No tienes proyectos activos en este momento.</p>';
    exit;
}

foreach ($projects as $project):
    $status      = $project['status'] ?? 'pendiente';
    $statusInfo  = PROJECT_STATUS_MAP[$status] ?? ['class' => 'badge--pendiente', 'label' => ucfirst($status)];
    $title       = htmlspecialchars($project['title'] ?? 'Sin título', ENT_QUOTES, 'UTF-8');
    $rawDate     = $project['created_at'] ?? $project['updated_at'] ?? '';
    $date        = $rawDate !== '' ? date('d/m/Y', strtotime($rawDate)) : '—';
?>
<div class="project-card">
    <div class="project-card__header">
        <h3 class="project-card__title"><?= $title ?></h3>
        <span class="badge <?= htmlspecialchars($statusInfo['class'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>
    <div class="project-card__footer">
        <span class="project-card__date"><?= $date ?></span>
    </div>
</div>
<?php endforeach; ?>
