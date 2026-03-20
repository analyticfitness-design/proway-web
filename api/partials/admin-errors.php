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
    $errors = $errorLogService->listRecent(50);
    $counts = $errorLogService->countByLevel();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los logs. Inténtalo de nuevo.</div>';
    exit;
}

// Build counts lookup
$countMap = [];
foreach ($counts as $row) {
    $countMap[$row['level']] = (int) $row['total'];
}
$totalErrors   = $countMap['error']   ?? 0;
$totalWarnings = $countMap['warning'] ?? 0;
$totalInfo     = $countMap['info']    ?? 0;

$levelBadge = [
    'error'   => 'background: #E31E24; color: #fff;',
    'warning' => 'background: #FBBF24; color: #000;',
    'info'    => 'background: #00D9FF; color: #000;',
];
?>

<div style="display: flex; gap: var(--pw-space-3); margin-bottom: var(--pw-space-4);">
    <div class="stat-card">
        <div class="stat-card__value" style="color: #E31E24;"><?= $totalErrors ?></div>
        <div class="stat-card__label">Errores</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__value" style="color: #FBBF24;"><?= $totalWarnings ?></div>
        <div class="stat-card__label">Warnings</div>
    </div>
    <div class="stat-card">
        <div class="stat-card__value" style="color: #00D9FF;"><?= $totalInfo ?></div>
        <div class="stat-card__label">Info</div>
    </div>
</div>

<?php if (empty($errors)): ?>
    <div class="alert alert--success">No hay errores registrados. ¡Todo en orden!</div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: var(--pw-space-2);">Nivel</th>
                    <th style="padding: var(--pw-space-2);">Mensaje</th>
                    <th style="padding: var(--pw-space-2);">URL</th>
                    <th style="padding: var(--pw-space-2);">Fecha</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errors as $err): ?>
                    <tr>
                        <td style="padding: var(--pw-space-2);">
                            <span style="<?= $levelBadge[$err['level']] ?? '' ?> padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
                                <?= htmlspecialchars($err['level']) ?>
                            </span>
                        </td>
                        <td style="padding: var(--pw-space-2); max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars(mb_substr($err['message'] ?? '', 0, 120)) ?>
                        </td>
                        <td style="padding: var(--pw-space-2); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.8rem; opacity: 0.7;">
                            <?= htmlspecialchars(mb_substr($err['url'] ?? '', 0, 80)) ?>
                        </td>
                        <td style="padding: var(--pw-space-2); white-space: nowrap; font-size: 0.8rem; opacity: 0.7;">
                            <?= htmlspecialchars($err['created_at'] ?? '') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
