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

use ProWay\Domain\Approval\ApprovalService;
use ProWay\Domain\Approval\MySQLApprovalRepository;

$approvalService = new ApprovalService(new MySQLApprovalRepository($pdo));

try {
    $approvals = $approvalService->listPendingAll();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar la cola de aprobaciones.</div>';
    exit;
}

$statusBadge = function (string $status): string {
    return match ($status) {
        'approved'          => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(0,255,135,0.15); color: var(--pw-accent-2, #00FF87);">Aprobado</span>',
        'changes_requested' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(227,30,36,0.15); color: var(--pw-danger, #E31E24);">Cambios Solicitados</span>',
        default             => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(255,193,7,0.15); color: #FFC107;">Pendiente</span>',
    };
};
?>

<div class="card">
    <div class="card__header">
        <h3 class="card__title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -3px;">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            Cola de Aprobaciones
        </h3>
    </div>

    <?php if (empty($approvals)): ?>
        <p style="padding: var(--pw-space-4); color: var(--pw-text-muted);">
            No hay aprobaciones pendientes.
        </p>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Proyecto</th>
                    <th>Entregable</th>
                    <th>Estado</th>
                    <th>Comentario</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($approvals as $a):
                    $clientName      = htmlspecialchars($a['client_name'] ?? $a['client_email'] ?? '—', ENT_QUOTES, 'UTF-8');
                    $projectTitle    = htmlspecialchars($a['project_title'] ?? $a['project_code'] ?? '—', ENT_QUOTES, 'UTF-8');
                    $deliverableTitle = htmlspecialchars($a['deliverable_title'] ?? '—', ENT_QUOTES, 'UTF-8');
                    $comment         = $a['comment'] ?? '';
                    $commentShort    = mb_strlen($comment) > 80 ? mb_substr($comment, 0, 80) . '...' : $comment;
                    $reviewedAt      = !empty($a['reviewed_at'])
                        ? date('d/m/Y H:i', strtotime($a['reviewed_at']))
                        : (!empty($a['created_at']) ? date('d/m/Y H:i', strtotime($a['created_at'])) : '—');
                    $projectId       = (int) ($a['project_id'] ?? 0);
                ?>
                <tr>
                    <td><?= $clientName ?></td>
                    <td>
                        <strong><?= $projectTitle ?></strong>
                    </td>
                    <td><?= $deliverableTitle ?></td>
                    <td><?= $statusBadge($a['status'] ?? 'pending') ?></td>
                    <td style="color: var(--pw-text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                        title="<?= htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($commentShort, ENT_QUOTES, 'UTF-8') ?: '<em>—</em>' ?>
                    </td>
                    <td style="white-space: nowrap;"><?= $reviewedAt ?></td>
                    <td>
                        <?php if ($projectId > 0): ?>
                        <a href="/admin?tab=projects&project_id=<?= $projectId ?>"
                           class="btn btn--ghost btn--sm"
                           title="Ver Proyecto">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                            Ver
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
