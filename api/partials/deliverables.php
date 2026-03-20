<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

use ProWay\Domain\Deliverable\DeliverableService;
use ProWay\Domain\Deliverable\MySQLDeliverableRepository;

$deliverableService = new DeliverableService(new MySQLDeliverableRepository($pdo));

$projectId = (int) ($_GET['project_id'] ?? 0);

if ($projectId === 0) {
    echo '<div class="alert alert--error">project_id es requerido.</div>';
    exit;
}

try {
    $deliverables = $deliverableService->listByProject($projectId);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los entregables.</div>';
    exit;
}

$isAdmin = $currentUser->type === 'admin';

$typeIcons = [
    'video'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
    'design'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
    'document' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    'archive'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
];
$defaultIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';
?>

<?php if ($isAdmin): ?>
<div class="card" style="margin-bottom: var(--pw-space-4);">
    <div class="card__header">
        <h3 class="card__title">Subir Entregable</h3>
    </div>
    <form id="upload-deliverable-form"
          hx-post="/api/v1/admin/deliverables"
          hx-encoding="multipart/form-data"
          hx-target="#deliverables-list"
          hx-swap="outerHTML"
          hx-indicator="#upload-spinner"
          style="padding: var(--pw-space-4); display: flex; flex-direction: column; gap: var(--pw-space-3);">

        <input type="hidden" name="project_id" value="<?= $projectId ?>">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--pw-space-3);">
            <div>
                <label class="form-label" for="del-title">Título</label>
                <input type="text" name="title" id="del-title" class="form-input" required placeholder="Nombre del entregable">
            </div>
            <div>
                <label class="form-label" for="del-type">Tipo</label>
                <select name="type" id="del-type" class="form-input" required>
                    <option value="">Seleccionar...</option>
                    <option value="video">Video</option>
                    <option value="design">Diseño</option>
                    <option value="document">Documento</option>
                    <option value="archive">Archivo</option>
                </select>
            </div>
        </div>

        <div>
            <label class="form-label" for="del-desc">Descripción (opcional)</label>
            <textarea name="description" id="del-desc" class="form-input" rows="2" placeholder="Descripción breve..."></textarea>
        </div>

        <div>
            <label class="form-label" for="del-file">Archivo</label>
            <input type="file" name="file" id="del-file" class="form-input" required
                   accept=".pdf,.zip,.mp4,.mov,.jpg,.jpeg,.png,.svg">
            <small style="color: var(--pw-text-muted);">Máximo 50 MB — PDF, ZIP, MP4, MOV, JPG, PNG, SVG</small>
        </div>

        <div style="display: flex; align-items: center; gap: var(--pw-space-2);">
            <button type="submit" class="btn btn--primary btn--sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Subir
            </button>
            <span id="upload-spinner" class="htmx-indicator" style="color: var(--pw-accent);">Subiendo...</span>
        </div>
    </form>
</div>
<?php endif; ?>

<div id="deliverables-list">
<?php if (empty($deliverables)): ?>
    <div style="padding: var(--pw-space-4); text-align: center; color: var(--pw-text-muted);">
        No hay entregables para este proyecto.
    </div>
<?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Título</th>
                    <th>Descripción</th>
                    <th>Versión</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($deliverables as $d): ?>
                <tr>
                    <td>
                        <span style="color: var(--pw-accent);" title="<?= htmlspecialchars($d['type']) ?>">
                            <?= $typeIcons[$d['type']] ?? $defaultIcon ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($d['title']) ?></td>
                    <td style="color: var(--pw-text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= htmlspecialchars($d['description'] ?? '—') ?>
                    </td>
                    <td>v<?= (int) $d['version'] ?></td>
                    <td style="white-space: nowrap;">
                        <?= $d['delivered_at'] ? date('d/m/Y', strtotime($d['delivered_at'])) : '—' ?>
                    </td>
                    <td>
                        <?php if (!empty($d['file_url'])): ?>
                        <a href="<?= htmlspecialchars($d['file_url']) ?>"
                           class="btn btn--ghost btn--sm"
                           download
                           title="Descargar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Descargar
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
