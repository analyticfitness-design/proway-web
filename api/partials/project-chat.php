<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

use ProWay\Domain\Message\MessageService;
use ProWay\Domain\Message\MySQLMessageRepository;

$messageService = new MessageService(new MySQLMessageRepository($pdo));

$projectId = (int) ($_GET['project_id'] ?? 0);

if ($projectId === 0) {
    echo '<div class="alert alert--error">project_id es requerido.</div>';
    exit;
}

try {
    // Auto-mark messages as read
    $messageService->markRead($projectId, $currentUser->type);
    $messages = $messageService->getMessages($projectId);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar los mensajes.</div>';
    exit;
}

$isAdmin = $currentUser->type === 'admin';
?>

<div class="chat-panel"
     id="chat-panel"
     hx-get="/api/partials/project-chat.php?project_id=<?= $projectId ?>"
     hx-trigger="every 10s"
     hx-target="#chat-panel"
     hx-swap="outerHTML">

    <div class="chat-panel__messages" id="chat-messages">
        <?php if (empty($messages)): ?>
            <div class="chat-panel__empty">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--pw-text-dim); margin-bottom: var(--pw-space-2);">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <p>No hay mensajes aún. Inicia la conversación.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg):
                $isMine = $msg['sender_type'] === $currentUser->type;
                $alignClass = $isMine ? 'chat-msg--right' : 'chat-msg--left';
                $senderLabel = $msg['sender_type'] === 'admin' ? 'ProWay Lab' : 'Cliente';
                $time = date('d M, H:i', strtotime($msg['created_at']));
            ?>
            <div class="chat-msg <?= $alignClass ?>">
                <div class="chat-msg__bubble">
                    <span class="chat-msg__sender"><?= htmlspecialchars($senderLabel) ?></span>
                    <p class="chat-msg__content"><?= nl2br(htmlspecialchars($msg['content'])) ?></p>
                    <time class="chat-msg__time" datetime="<?= htmlspecialchars($msg['created_at']) ?>"><?= $time ?></time>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form class="chat-panel__form"
          hx-post="/api/v1/projects/<?= $projectId ?>/messages"
          hx-target="#chat-panel"
          hx-swap="outerHTML"
          hx-vals='{"_partial": "1"}'
          hx-on::after-request="this.reset(); document.getElementById('chat-messages')?.scrollTo(0, document.getElementById('chat-messages')?.scrollHeight);">

        <input type="hidden" name="project_id" value="<?= $projectId ?>">

        <div class="chat-panel__input-row">
            <input type="text"
                   name="content"
                   class="form-input chat-panel__input"
                   placeholder="Escribe un mensaje..."
                   required
                   autocomplete="off"
                   maxlength="2000">
            <button type="submit" class="btn btn--primary btn--sm chat-panel__send" title="Enviar mensaje">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </div>
    </form>
</div>

<style>
.chat-panel {
    display: flex;
    flex-direction: column;
    height: 480px;
    border: 1px solid var(--pw-border, rgba(255,255,255,0.08));
    border-radius: var(--pw-radius, 12px);
    background: var(--pw-surface, rgba(255,255,255,0.03));
    overflow: hidden;
}

.chat-panel__messages {
    flex: 1;
    overflow-y: auto;
    padding: var(--pw-space-4, 1rem);
    display: flex;
    flex-direction: column;
    gap: var(--pw-space-3, 0.75rem);
}

.chat-panel__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--pw-text-muted, #888);
    text-align: center;
}

.chat-panel__empty p {
    margin: 0;
    font-size: 0.875rem;
}

/* ── Message bubbles ── */
.chat-msg {
    display: flex;
    max-width: 80%;
}

.chat-msg--left {
    align-self: flex-start;
}

.chat-msg--right {
    align-self: flex-end;
}

.chat-msg__bubble {
    padding: var(--pw-space-2, 0.5rem) var(--pw-space-3, 0.75rem);
    border-radius: var(--pw-radius, 12px);
    font-size: 0.875rem;
    line-height: 1.5;
    word-break: break-word;
}

.chat-msg--left .chat-msg__bubble {
    background: var(--pw-surface-alt, rgba(255,255,255,0.06));
    border-bottom-left-radius: 4px;
}

.chat-msg--right .chat-msg__bubble {
    background: rgba(0, 245, 212, 0.1);
    border: 1px solid rgba(0, 245, 212, 0.2);
    border-bottom-right-radius: 4px;
}

.chat-msg__sender {
    display: block;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 2px;
}

.chat-msg--left .chat-msg__sender {
    color: var(--pw-text-muted, #888);
}

.chat-msg--right .chat-msg__sender {
    color: var(--pw-accent, #00f5d4);
}

.chat-msg__content {
    margin: 0;
    color: var(--pw-text, #e0e0e0);
}

.chat-msg__time {
    display: block;
    font-size: 0.675rem;
    color: var(--pw-text-dim, #666);
    margin-top: 4px;
    text-align: right;
}

/* ── Input area ── */
.chat-panel__form {
    border-top: 1px solid var(--pw-border, rgba(255,255,255,0.08));
    padding: var(--pw-space-3, 0.75rem);
    background: var(--pw-surface, rgba(255,255,255,0.03));
}

.chat-panel__input-row {
    display: flex;
    gap: var(--pw-space-2, 0.5rem);
    align-items: center;
}

.chat-panel__input {
    flex: 1;
    min-width: 0;
}

.chat-panel__send {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.75rem;
}

/* ── Scrollbar styling ── */
.chat-panel__messages::-webkit-scrollbar {
    width: 6px;
}
.chat-panel__messages::-webkit-scrollbar-track {
    background: transparent;
}
.chat-panel__messages::-webkit-scrollbar-thumb {
    background: var(--pw-border, rgba(255,255,255,0.1));
    border-radius: 3px;
}
.chat-panel__messages::-webkit-scrollbar-thumb:hover {
    background: var(--pw-text-dim, rgba(255,255,255,0.2));
}
</style>

<script>
// Auto-scroll to bottom on load
(function() {
    const el = document.getElementById('chat-messages');
    if (el) el.scrollTop = el.scrollHeight;
})();
</script>
