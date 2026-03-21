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

$STATUS_META = [
    'cotizacion'    => ['label' => 'Cotización',     'color' => '#6b7280'],
    'confirmado'    => ['label' => 'Confirmado',     'color' => '#06b6d4'],
    'en_produccion' => ['label' => 'En Producción',  'color' => '#22c55e'],
    'revision'      => ['label' => 'Revisión',       'color' => '#eab308'],
    'entregado'     => ['label' => 'Entregado',      'color' => '#a855f7'],
    'facturado'     => ['label' => 'Facturado',      'color' => '#f97316'],
    'pagado'        => ['label' => 'Pagado',         'color' => '#10b981'],
];

try {
    $columns = $projectService->listGroupedByStatus();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar el tablero Kanban. Inténtalo de nuevo.</div>';
    exit;
}
?>

<div class="kanban" x-data="kanbanBoard()" id="kanban-board"
     hx-get="/api/partials/admin-kanban.php"
     hx-trigger="refresh"
     hx-swap="innerHTML"
     hx-target="#kanban-container"
     hx-headers='{"X-Requested-With": "XMLHttpRequest"}'>

    <div class="kanban__columns">
        <?php foreach ($STATUS_META as $statusKey => $meta):
            $cards = $columns[$statusKey] ?? [];
            $count = count($cards);
            $color = $meta['color'];
            $label = $meta['label'];
        ?>
        <div class="kanban__column"
             @dragover.prevent="dragOver($event)"
             @drop="drop($event, '<?= $statusKey ?>')"
             data-status="<?= $statusKey ?>">

            <div class="kanban__column-header">
                <div class="kanban__column-title">
                    <span class="kanban__column-dot" style="background: <?= $color ?>;"></span>
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <span class="kanban__column-count"><?= $count ?></span>
            </div>

            <div class="kanban__cards" data-status="<?= $statusKey ?>">
                <?php foreach ($cards as $idx => $proj):
                    $title    = htmlspecialchars($proj['title'] ?? $proj['service_type'] ?? '—', ENT_QUOTES, 'UTF-8');
                    $code     = htmlspecialchars($proj['project_code'] ?? '', ENT_QUOTES, 'UTF-8');
                    $client   = htmlspecialchars($proj['client_name'] ?? $proj['client_code'] ?? '—', ENT_QUOTES, 'UTF-8');
                    $deadline = isset($proj['deadline']) ? date('d/m/Y', strtotime($proj['deadline'])) : null;
                    $isOverdue = $deadline && strtotime($proj['deadline']) < time() && !in_array($statusKey, ['entregado', 'facturado', 'pagado']);
                ?>
                <div class="kanban__card"
                     draggable="true"
                     @dragstart="dragStart($event, <?= (int)$proj['id'] ?>)"
                     @dragend="dragEnd($event)"
                     data-id="<?= (int)$proj['id'] ?>"
                     data-order="<?= $idx ?>"
                     style="--card-accent: <?= $color ?>;">

                    <div class="kanban__card-code"><?= $code ?></div>
                    <div class="kanban__card-title"><?= $title ?></div>
                    <div class="kanban__card-client">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                        <?= $client ?>
                    </div>
                    <?php if ($deadline): ?>
                    <div class="kanban__card-deadline <?= $isOverdue ? 'kanban__card-deadline--overdue' : '' ?>">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <?= $deadline ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <?php if (empty($cards)): ?>
                <div class="kanban__empty">Sin proyectos</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
/* ── Kanban Board ─────────────────────────────────────────────── */
.kanban {
    width: 100%;
    overflow-x: auto;
    padding-bottom: var(--pw-space-4);
    -webkit-overflow-scrolling: touch;
}

.kanban__columns {
    display: flex;
    gap: var(--pw-space-3);
    min-width: max-content;
    padding: var(--pw-space-1);
}

.kanban__column {
    width: 280px;
    min-width: 280px;
    flex-shrink: 0;
    background: var(--pw-panel);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-lg);
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 200px);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.kanban__column.drag-over {
    border-color: var(--pw-accent);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.kanban__column-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--pw-space-3) var(--pw-space-3);
    border-bottom: 1px solid var(--pw-border);
    position: sticky;
    top: 0;
    background: var(--pw-panel);
    border-radius: var(--pw-radius-lg) var(--pw-radius-lg) 0 0;
    z-index: 1;
}

.kanban__column-title {
    display: flex;
    align-items: center;
    gap: var(--pw-space-2);
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--pw-text);
}

.kanban__column-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.kanban__column-count {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--pw-text-muted);
    background: var(--pw-border);
    padding: 2px 8px;
    border-radius: var(--pw-radius-full);
}

.kanban__cards {
    flex: 1;
    overflow-y: auto;
    padding: var(--pw-space-2);
    display: flex;
    flex-direction: column;
    gap: var(--pw-space-2);
    min-height: 60px;
}

/* ── Card ─────────────────────────────────────────────────────── */
.kanban__card {
    background: var(--pw-bg);
    border: 1px solid var(--pw-border);
    border-left: 3px solid var(--card-accent, var(--pw-accent));
    border-radius: var(--pw-radius-md);
    padding: var(--pw-space-3);
    cursor: grab;
    transition: transform 0.15s, box-shadow 0.15s, opacity 0.15s;
    user-select: none;
}

.kanban__card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    transform: translateY(-1px);
}

.kanban__card:active {
    cursor: grabbing;
}

.kanban__card.dragging {
    opacity: 0.4;
    transform: rotate(2deg);
}

.kanban__card-code {
    font-size: 0.7rem;
    font-family: var(--pw-font-mono, monospace);
    color: var(--pw-text-muted);
    margin-bottom: 4px;
}

.kanban__card-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--pw-text);
    margin-bottom: 6px;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.kanban__card-client {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    color: var(--pw-text-muted);
    margin-bottom: 4px;
}

.kanban__card-deadline {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    color: var(--pw-text-muted);
    margin-top: 4px;
}

.kanban__card-deadline--overdue {
    color: #ef4444;
    font-weight: 600;
}

.kanban__empty {
    text-align: center;
    padding: var(--pw-space-4) var(--pw-space-2);
    font-size: 0.8rem;
    color: var(--pw-text-muted);
    font-style: italic;
}

/* ── Scrollbar inside columns ─────────────────────────────────── */
.kanban__cards::-webkit-scrollbar {
    width: 4px;
}
.kanban__cards::-webkit-scrollbar-track {
    background: transparent;
}
.kanban__cards::-webkit-scrollbar-thumb {
    background: var(--pw-border);
    border-radius: 2px;
}

/* ── Mobile ───────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .kanban__column {
        width: 260px;
        min-width: 260px;
    }
    .kanban__card {
        padding: var(--pw-space-2);
    }
}
</style>
