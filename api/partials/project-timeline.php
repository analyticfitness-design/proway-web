<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

$projectId = (int) ($_GET['project_id'] ?? 0);
if ($projectId <= 0) {
    echo '<div class="alert alert--error">ID de proyecto inválido.</div>';
    exit;
}

try {
    $entries = $activityService->getTimeline($projectId);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar la línea de tiempo.</div>';
    exit;
}

if (empty($entries)) {
    echo '<div class="timeline-empty">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--pw-text-dim);margin-bottom:var(--pw-space-2);">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <p>Sin actividad registrada</p>
    </div>';
    exit;
}

?>
<div class="timeline">
<?php foreach ($entries as $entry):
    $iconSvg = match ($entry['action']) {
        'status_change'    => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'invoice_created'  => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'project_created'  => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'note_added'       => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        default            => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>',
    };
    $dotClass = match ($entry['action']) {
        'status_change'   => 'timeline__dot--accent',
        'invoice_created' => 'timeline__dot--warning',
        'project_created' => 'timeline__dot--success',
        default           => '',
    };
?>
    <div class="timeline__item">
        <div class="timeline__dot <?= $dotClass ?>"><?= $iconSvg ?></div>
        <div class="timeline__content">
            <p class="timeline__action"><?= htmlspecialchars($entry['description'] ?: $entry['action']) ?></p>
            <time class="timeline__time" datetime="<?= $entry['created_at'] ?>"><?= date('d M Y, H:i', strtotime($entry['created_at'])) ?></time>
        </div>
    </div>
<?php endforeach; ?>
</div>
