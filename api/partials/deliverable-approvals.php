<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

use ProWay\Domain\Approval\ApprovalService;
use ProWay\Domain\Approval\MySQLApprovalRepository;
use ProWay\Domain\Deliverable\DeliverableService;
use ProWay\Domain\Deliverable\MySQLDeliverableRepository;

$approvalService    = new ApprovalService(new MySQLApprovalRepository($pdo));
$deliverableService = new DeliverableService(new MySQLDeliverableRepository($pdo));

$projectId = (int) ($_GET['project_id'] ?? 0);

if ($projectId === 0) {
    echo '<div class="alert alert--error">project_id es requerido.</div>';
    exit;
}

try {
    $deliverables = $deliverableService->listByProject($projectId);
    $approvals    = $approvalService->listByProject($projectId);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar las aprobaciones.</div>';
    exit;
}

// Index approvals by deliverable_id + client_id for quick lookup
$approvalIndex = [];
foreach ($approvals as $a) {
    $key = $a['deliverable_id'] . '-' . $a['client_id'];
    $approvalIndex[$key] = $a;
}

$isClient = $currentUser->type === 'client';

$statusBadge = function (string $status): string {
    return match ($status) {
        'approved'          => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(0,255,135,0.15); color: var(--pw-accent-2, #00FF87);">Aprobado</span>',
        'changes_requested' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(227,30,36,0.15); color: var(--pw-danger, #E31E24);">Cambios Solicitados</span>',
        default             => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(255,193,7,0.15); color: #FFC107;">Pendiente</span>',
    };
};
?>

<div id="deliverable-approvals" class="card" style="margin-top: var(--pw-space-4);">
    <div class="card__header">
        <h3 class="card__title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -3px;">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            Revisión de Entregables
        </h3>
    </div>

    <?php if (empty($deliverables)): ?>
        <p style="padding: var(--pw-space-4); color: var(--pw-text-muted);">
            No hay entregables para revisar en este proyecto.
        </p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: var(--pw-space-3); padding: var(--pw-space-4);">
        <?php foreach ($deliverables as $d):
            $dId  = (int) $d['id'];
            $key  = $dId . '-' . $currentUser->id;
            $approval = $approvalIndex[$key] ?? null;
            $currentStatus = $approval['status'] ?? 'pending';
        ?>
            <div style="border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: var(--pw-space-3); background: rgba(255,255,255,0.02);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--pw-space-2);">
                    <div>
                        <strong style="color: var(--pw-text);"><?= htmlspecialchars($d['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span style="color: var(--pw-text-muted); font-size: 0.85rem; margin-left: 8px;">
                            v<?= (int) $d['version'] ?>
                        </span>
                    </div>
                    <?= $statusBadge($currentStatus) ?>
                </div>

                <?php if (!empty($d['description'])): ?>
                <p style="color: var(--pw-text-muted); font-size: 0.85rem; margin-bottom: var(--pw-space-2);">
                    <?= htmlspecialchars($d['description'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>

                <?php if ($approval && !empty($approval['comment'])): ?>
                <div style="margin-bottom: var(--pw-space-2); padding: var(--pw-space-2); background: rgba(255,255,255,0.04); border-radius: 6px; font-size: 0.85rem;">
                    <strong style="color: var(--pw-text-muted);">Comentario:</strong>
                    <span style="color: var(--pw-text);"><?= htmlspecialchars($approval['comment'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php endif; ?>

                <?php if ($isClient): ?>
                <form hx-post="/api/v1/deliverables/<?= $dId ?>/approve"
                      hx-target="#deliverable-approvals"
                      hx-swap="outerHTML"
                      hx-indicator="#approval-spinner-<?= $dId ?>"
                      style="display: flex; flex-direction: column; gap: var(--pw-space-2); margin-top: var(--pw-space-2);">

                    <textarea name="comment"
                              class="form-input"
                              rows="2"
                              placeholder="Comentario (opcional)..."
                              style="font-size: 0.85rem;"><?= htmlspecialchars($approval['comment'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>

                    <div style="display: flex; align-items: center; gap: var(--pw-space-2);">
                        <button type="submit"
                                name="status"
                                value="approved"
                                class="btn btn--sm"
                                style="background: var(--pw-accent-2, #00FF87); color: #000; font-weight: 600;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Aprobar
                        </button>
                        <button type="submit"
                                name="status"
                                value="changes_requested"
                                class="btn btn--sm"
                                style="background: rgba(227,30,36,0.15); color: var(--pw-danger, #E31E24); font-weight: 600; border: 1px solid rgba(227,30,36,0.3);">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            Solicitar Cambios
                        </button>
                        <span id="approval-spinner-<?= $dId ?>" class="htmx-indicator" style="color: var(--pw-accent);">Enviando...</span>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
