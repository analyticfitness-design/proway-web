<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

use ProWay\Domain\Asset\AssetService;
use ProWay\Domain\Asset\MySQLAssetRepository;

$isAdmin = $currentUser->type === 'admin';
$id      = (int) ($_GET['id'] ?? 0);

if ($id === 0) {
    echo '<div class="alert alert--error">Asset ID requerido.</div>';
    exit;
}

try {
    $assetService = new AssetService(new MySQLAssetRepository($pdo));
    $asset = $assetService->getAsset($id);
    $allTags = $isAdmin ? $assetService->getTags() : [];
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar el asset.</div>';
    exit;
}

if ($asset === null) {
    echo '<div class="alert alert--error">Asset no encontrado.</div>';
    exit;
}

// Check client access
if (!$isAdmin && ($asset['client_id'] ?? 0) != $currentUser->id) {
    echo '<div class="alert alert--error">No tienes acceso a este asset.</div>';
    exit;
}

$ext     = strtolower(pathinfo($asset['file_url'] ?? '', PATHINFO_EXTENSION));
$isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'webp', 'gif']);
$isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
$isPdf   = $ext === 'pdf';
$tags    = $asset['tags'] ?? [];
$tagIds  = array_column($tags, 'id');
?>

<div class="apm__overlay"
     x-data="assetPreview()"
     x-show="open"
     x-init="open = true"
     @click.self="close()"
     @keydown.escape.window="close()"
     x-transition:enter="fade-in"
     x-transition:leave="fade-out"
     x-cloak>

    <div class="apm__modal">
        <!-- Header -->
        <div class="apm__header">
            <div>
                <h3 class="apm__title"><?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <span class="apm__subtitle">
                    <?= htmlspecialchars($asset['project_code'] ?? $asset['project_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($isAdmin && !empty($asset['client_name'])): ?>
                    &mdash; <?= htmlspecialchars($asset['client_name'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </span>
            </div>
            <button class="btn btn--ghost btn--sm" @click="close()" title="Cerrar">&times;</button>
        </div>

        <!-- Preview area -->
        <div class="apm__preview">
            <?php if ($isImage): ?>
            <img src="<?= htmlspecialchars($asset['file_url'], ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?>"
                 class="apm__image">

            <?php elseif ($isVideo): ?>
            <video controls class="apm__video" preload="metadata">
                <source src="<?= htmlspecialchars($asset['file_url'], ENT_QUOTES, 'UTF-8') ?>" type="video/<?= $ext === 'mov' ? 'quicktime' : $ext ?>">
                Tu navegador no soporta la reproduccion de video.
            </video>

            <?php elseif ($isPdf): ?>
            <div class="apm__file-preview">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: #ef4444;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <span>Documento PDF</span>
            </div>

            <?php else: ?>
            <div class="apm__file-preview">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--pw-text-muted);">
                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
                    <polyline points="13 2 13 9 20 9"/>
                </svg>
                <span><?= htmlspecialchars(strtoupper($ext ?: 'Archivo'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="apm__details">
            <?php if (!empty($asset['description'])): ?>
            <p class="apm__desc"><?= nl2br(htmlspecialchars($asset['description'], ENT_QUOTES, 'UTF-8')) ?></p>
            <?php endif; ?>

            <div class="apm__meta-grid">
                <div class="apm__meta">
                    <span class="apm__meta-label">Tipo</span>
                    <span class="apm__meta-value"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $asset['type'])), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="apm__meta">
                    <span class="apm__meta-label">Version</span>
                    <span class="apm__meta-value">v<?= (int) ($asset['version'] ?? 1) ?></span>
                </div>
                <div class="apm__meta">
                    <span class="apm__meta-label">Entregado</span>
                    <span class="apm__meta-value"><?= date('d M Y', strtotime($asset['delivered_at'])) ?></span>
                </div>
            </div>

            <!-- Tags (current) -->
            <div class="apm__tags-section">
                <span class="apm__meta-label">Etiquetas</span>
                <div class="apm__tags" id="apm-tags-<?= $id ?>">
                    <?php if (empty($tags)): ?>
                    <span class="apm__no-tags">Sin etiquetas</span>
                    <?php else: ?>
                    <?php foreach ($tags as $tag): ?>
                    <span class="alib__tag"><?= htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isAdmin && !empty($allTags)): ?>
            <!-- Tag management (admin only) -->
            <div class="apm__tag-manage">
                <span class="apm__meta-label">Gestionar etiquetas</span>
                <div class="apm__tag-checkboxes">
                    <?php foreach ($allTags as $tag): ?>
                    <label class="apm__tag-check">
                        <input type="checkbox"
                               value="<?= (int) $tag['id'] ?>"
                               <?= in_array((int) $tag['id'], $tagIds) ? 'checked' : '' ?>
                               @change="toggleTag(<?= $id ?>, $event)">
                        <?= htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="apm__tag-new" style="margin-top:6px;">
                    <input type="text"
                           placeholder="Nueva etiqueta..."
                           class="alib__search"
                           style="padding:4px 8px;font-size:0.78rem;width:auto;flex:1;"
                           x-model="newTag"
                           @keydown.enter.prevent="createTag(<?= $id ?>)">
                    <button class="btn btn--ghost btn--sm" @click="createTag(<?= $id ?>)" style="font-size:0.72rem;">+ Crear</button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer with actions -->
        <div class="apm__footer">
            <button class="btn btn--ghost btn--sm" @click="close()">Cerrar</button>
            <?php if (!empty($asset['file_url'])): ?>
            <a href="<?= htmlspecialchars($asset['file_url'], ENT_QUOTES, 'UTF-8') ?>"
               download
               class="btn btn--primary btn--sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Descargar
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ── Asset Preview Modal ────────────────────────────────────────────────── */
.apm__overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: var(--pw-space-4);
}

.apm__modal {
    background: var(--pw-panel);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-lg);
    width: 100%;
    max-width: 640px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
}

.apm__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: var(--pw-space-3) var(--pw-space-4);
    border-bottom: 1px solid var(--pw-border);
}

.apm__title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--pw-text);
    margin: 0;
}

.apm__subtitle {
    font-size: 0.78rem;
    color: var(--pw-accent, #00D9FF);
}

/* ── Preview ────────────────────────────────────────────────────────────── */
.apm__preview {
    background: var(--pw-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    max-height: 400px;
    overflow: hidden;
}

.apm__image {
    width: 100%;
    height: auto;
    max-height: 400px;
    object-fit: contain;
}

.apm__video {
    width: 100%;
    max-height: 400px;
    background: #000;
}

.apm__file-preview {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--pw-space-2);
    padding: var(--pw-space-6);
    color: var(--pw-text-muted);
    font-size: 0.85rem;
}

/* ── Details ────────────────────────────────────────────────────────────── */
.apm__details {
    padding: var(--pw-space-3) var(--pw-space-4);
    display: flex;
    flex-direction: column;
    gap: var(--pw-space-3);
}

.apm__desc {
    font-size: 0.85rem;
    color: var(--pw-text-muted);
    margin: 0;
    line-height: 1.5;
}

.apm__meta-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--pw-space-2);
}

.apm__meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.apm__meta-label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--pw-text-muted);
}

.apm__meta-value {
    font-size: 0.85rem;
    color: var(--pw-text);
    font-weight: 500;
}

/* ── Tags section ───────────────────────────────────────────────────────── */
.apm__tags-section {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.apm__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.apm__no-tags {
    font-size: 0.78rem;
    color: var(--pw-text-muted);
    font-style: italic;
}

/* ── Tag management ─────────────────────────────────────────────────────── */
.apm__tag-manage {
    background: var(--pw-bg);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-md);
    padding: var(--pw-space-2) var(--pw-space-3);
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.apm__tag-checkboxes {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.apm__tag-check {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.78rem;
    color: var(--pw-text);
    cursor: pointer;
}

.apm__tag-check input[type="checkbox"] {
    accent-color: var(--pw-accent, #00D9FF);
    cursor: pointer;
}

.apm__tag-new {
    display: flex;
    align-items: center;
    gap: var(--pw-space-2);
}

/* ── Footer ─────────────────────────────────────────────────────────────── */
.apm__footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: var(--pw-space-2);
    padding: var(--pw-space-3) var(--pw-space-4);
    border-top: 1px solid var(--pw-border);
}

/* ── Tag chip (reused from library) ─────────────────────────────────────── */
.alib__tag {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 0.62rem;
    font-weight: 600;
    background: rgba(0, 217, 255, 0.1);
    color: var(--pw-accent, #00D9FF);
    border: 1px solid rgba(0, 217, 255, 0.2);
    white-space: nowrap;
}

/* ── Transitions ────────────────────────────────────────────────────────── */
.fade-in  { animation: apm-fade-in 0.15s ease; }
.fade-out { animation: apm-fade-out 0.15s ease; }
@keyframes apm-fade-in  { from { opacity: 0; } to { opacity: 1; } }
@keyframes apm-fade-out { from { opacity: 1; } to { opacity: 0; } }
[x-cloak] { display: none !important; }

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 640px) {
    .apm__modal { max-width: 100%; margin: var(--pw-space-2); }
    .apm__meta-grid { grid-template-columns: 1fr; }
}
</style>

<script>
function assetPreview() {
    return {
        open: false,
        newTag: '',

        close() {
            this.open = false;
            var container = document.getElementById('asset-preview-container');
            setTimeout(function() {
                if (container) container.textContent = '';
            }, 200);
        },

        async toggleTag(assetId, event) {
            var checkboxes = this.$el.querySelectorAll('.apm__tag-checkboxes input[type="checkbox"]');
            var tagIds = [];
            checkboxes.forEach(function(cb) {
                if (cb.checked) tagIds.push(parseInt(cb.value));
            });

            try {
                var res = await fetch('/api/v1/admin/assets/' + assetId + '/tags', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tag_ids: tagIds }),
                });
                if (res.status === 401) { window.location.href = '/login'; return; }
                var json = await res.json();
                if (json.success) {
                    // Refresh the modal to reflect updated tags
                    htmx.ajax('GET', '/api/partials/asset-preview-modal.php?id=' + assetId, '#asset-preview-container');
                }
            } catch (e) {
                console.error('Error updating tags:', e);
            }
        },

        async createTag(assetId) {
            var name = this.newTag.trim();
            if (!name) return;

            try {
                var res = await fetch('/api/v1/admin/assets/tags', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name }),
                });
                if (res.status === 401) { window.location.href = '/login'; return; }
                var json = await res.json();
                if (json.success && json.data && json.data.tag) {
                    this.newTag = '';
                    // Reload the preview modal to refresh tag list
                    htmx.ajax('GET', '/api/partials/asset-preview-modal.php?id=' + assetId, '#asset-preview-container');
                } else {
                    alert(json.error?.message || 'Error al crear la etiqueta.');
                }
            } catch (e) {
                alert('Error de conexion.');
            }
        },
    };
}
</script>
