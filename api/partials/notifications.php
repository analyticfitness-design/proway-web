<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $notifications = $notifService->listForUser($currentUser->type, $currentUser->id);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar notificaciones.</div>';
    exit;
}

if (empty($notifications)) {
    echo '<div class="notif-empty">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--pw-text-dim);margin-bottom:var(--pw-space-2);">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <p>No tienes notificaciones</p>
    </div>';
    exit;
}

foreach ($notifications as $n): ?>
<div class="notif-item <?= $n['is_read'] ? '' : 'notif-item--unread' ?>"
     id="notif-<?= $n['id'] ?>">
    <div class="notif-item__icon">
        <?php
        $icon = match ($n['type']) {
            'invoice'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
            'project'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
            default    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        };
        echo $icon;
        ?>
    </div>
    <div class="notif-item__body">
        <p class="notif-item__title"><?= htmlspecialchars($n['title']) ?></p>
        <?php if (!empty($n['message'])): ?>
            <p class="notif-item__msg"><?= htmlspecialchars($n['message']) ?></p>
        <?php endif; ?>
        <time class="notif-item__time" datetime="<?= $n['created_at'] ?>"><?= date('d M, H:i', strtotime($n['created_at'])) ?></time>
    </div>
    <?php if (!$n['is_read']): ?>
    <button class="notif-item__mark"
            title="Marcar como leída"
            hx-patch="/api/v1/notifications/<?= $n['id'] ?>/read"
            hx-target="#notif-<?= $n['id'] ?>"
            hx-swap="outerHTML"
            hx-on::after-request="htmx.trigger('#notif-badge','refreshBadge')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </button>
    <?php endif; ?>
</div>
<?php endforeach;
