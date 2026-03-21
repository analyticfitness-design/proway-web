<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

use ProWay\Domain\Asset\AssetService;
use ProWay\Domain\Asset\MySQLAssetRepository;

$isAdmin  = $currentUser->type === 'admin';

// ── Filters ────────────────────────────────────────────────────────────────
$filters = [];
if (!$isAdmin) {
    $filters['client_id'] = $currentUser->id;
}
if (!empty($_GET['client_id']) && $isAdmin) {
    $filters['client_id'] = (int) $_GET['client_id'];
}
if (!empty($_GET['type'])) {
    $filters['type'] = $_GET['type'];
}
if (!empty($_GET['tag_id'])) {
    $filters['tag_id'] = (int) $_GET['tag_id'];
}
if (!empty($_GET['q'])) {
    $filters['q'] = trim($_GET['q']);
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 24;

// ── Fetch data ─────────────────────────────────────────────────────────────
try {
    $assetService = new AssetService(new MySQLAssetRepository($pdo));
    $result = $assetService->search($filters, $page, $perPage);
    $assets = $result['items'];
    $total  = $result['total'];
    $pages  = $result['pages'];
    $tags   = $assetService->getTags();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar la biblioteca de assets.</div>';
    exit;
}

// ── Clients for admin filter ───────────────────────────────────────────────
$clients = [];
if ($isAdmin) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM clients WHERE status = 'activo' ORDER BY name ASC");
        $clients = $stmt->fetchAll();
    } catch (Throwable) {}
}

// Type icons
$typeIcons = [
    'video'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
    'thumbnail'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
    'copy'        => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    'brand_asset' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
    'revision'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
    'final'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
];
$defaultIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>';

$typeColors = [
    'video'       => '#00D9FF',
    'thumbnail'   => '#A855F7',
    'copy'        => '#22C55E',
    'brand_asset' => '#FBBF24',
    'revision'    => '#F97316',
    'final'       => '#06B6D4',
];

// Build query string for pagination
$qs = [];
if (!empty($filters['client_id']) && $isAdmin) $qs[] = 'client_id=' . $filters['client_id'];
if (!empty($filters['type']))                   $qs[] = 'type=' . urlencode($filters['type']);
if (!empty($filters['tag_id']))                 $qs[] = 'tag_id=' . $filters['tag_id'];
if (!empty($filters['q']))                      $qs[] = 'q=' . urlencode($filters['q']);
$qsBase = $qs ? '&' . implode('&', $qs) : '';
?>

<!-- Search & Filters -->
<div class="alib__toolbar">
    <div class="alib__search-wrap">
        <svg class="alib__search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="search"
               class="alib__search"
               placeholder="Buscar assets..."
               value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               hx-get="/api/partials/asset-library.php"
               hx-trigger="input changed delay:400ms, search"
               hx-target="#asset-library-root"
               hx-swap="innerHTML"
               hx-include="[name='type'],[name='tag_id'],[name='client_id']"
               name="q">
    </div>

    <div class="alib__filters">
        <?php if ($isAdmin && !empty($clients)): ?>
        <select name="client_id" class="alib__select"
                hx-get="/api/partials/asset-library.php"
                hx-trigger="change"
                hx-target="#asset-library-root"
                hx-swap="innerHTML"
                hx-include="[name='q'],[name='type'],[name='tag_id']">
            <option value="">Todos los clientes</option>
            <?php foreach ($clients as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= ($filters['client_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <select name="type" class="alib__select"
                hx-get="/api/partials/asset-library.php"
                hx-trigger="change"
                hx-target="#asset-library-root"
                hx-swap="innerHTML"
                hx-include="[name='q'],[name='tag_id'],[name='client_id']">
            <option value="">Todos los tipos</option>
            <option value="video"       <?= ($filters['type'] ?? '') === 'video' ? 'selected' : '' ?>>Video</option>
            <option value="thumbnail"   <?= ($filters['type'] ?? '') === 'thumbnail' ? 'selected' : '' ?>>Thumbnail</option>
            <option value="copy"        <?= ($filters['type'] ?? '') === 'copy' ? 'selected' : '' ?>>Copy</option>
            <option value="brand_asset" <?= ($filters['type'] ?? '') === 'brand_asset' ? 'selected' : '' ?>>Brand Asset</option>
            <option value="revision"    <?= ($filters['type'] ?? '') === 'revision' ? 'selected' : '' ?>>Revision</option>
            <option value="final"       <?= ($filters['type'] ?? '') === 'final' ? 'selected' : '' ?>>Final</option>
        </select>

        <?php if (!empty($tags)): ?>
        <select name="tag_id" class="alib__select"
                hx-get="/api/partials/asset-library.php"
                hx-trigger="change"
                hx-target="#asset-library-root"
                hx-swap="innerHTML"
                hx-include="[name='q'],[name='type'],[name='client_id']">
            <option value="">Todas las etiquetas</option>
            <?php foreach ($tags as $tag): ?>
            <option value="<?= (int) $tag['id'] ?>" <?= ($filters['tag_id'] ?? '') == $tag['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $tag['usage_count'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>
</div>

<!-- Results count -->
<div class="alib__count">
    <?= $total ?> asset<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
</div>

<?php if (empty($assets)): ?>
<div class="alib__empty">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.3;">
        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
    </svg>
    <p>No se encontraron assets con los filtros seleccionados.</p>
</div>
<?php else: ?>

<!-- Asset Grid -->
<div class="alib__grid">
    <?php foreach ($assets as $asset):
        $icon    = $typeIcons[$asset['type']] ?? $defaultIcon;
        $color   = $typeColors[$asset['type']] ?? '#6B7280';
        $hasThumb = !empty($asset['thumbnail_url']) || !empty($asset['preview_url']);
        $thumbUrl = $asset['thumbnail_url'] ?: $asset['preview_url'] ?: '';
        $ext     = strtolower(pathinfo($asset['file_url'] ?? '', PATHINFO_EXTENSION));
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'webp', 'gif']);
        $isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
        $tagList = $asset['tag_names'] ? explode(', ', $asset['tag_names']) : [];
    ?>
    <div class="alib__card"
         hx-get="/api/partials/asset-preview-modal.php?id=<?= (int) $asset['id'] ?>"
         hx-target="#asset-preview-container"
         hx-swap="innerHTML"
         title="Ver detalles">

        <!-- Thumbnail area -->
        <div class="alib__thumb" style="--type-color: <?= $color ?>;">
            <?php if ($hasThumb || $isImage): ?>
            <img src="<?= htmlspecialchars($thumbUrl ?: $asset['file_url'], ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?>"
                 loading="lazy"
                 class="alib__thumb-img">
            <?php elseif ($isVideo): ?>
            <div class="alib__thumb-icon alib__thumb-icon--video">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="5 3 19 12 5 21 5 3"/>
                </svg>
            </div>
            <?php else: ?>
            <div class="alib__thumb-icon">
                <?= $icon ?>
            </div>
            <?php endif; ?>

            <!-- Type badge -->
            <span class="alib__type-badge" style="background:<?= $color ?>;">
                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $asset['type'])), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <!-- Card body -->
        <div class="alib__card-body">
            <h4 class="alib__card-title"><?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?></h4>
            <span class="alib__card-project"><?= htmlspecialchars($asset['project_code'] ?? $asset['project_title'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($isAdmin): ?>
            <span class="alib__card-client"><?= htmlspecialchars($asset['client_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>

            <?php if (!empty($tagList)): ?>
            <div class="alib__card-tags">
                <?php foreach (array_slice($tagList, 0, 3) as $tag): ?>
                <span class="alib__tag"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
                <?php if (count($tagList) > 3): ?>
                <span class="alib__tag alib__tag--more">+<?= count($tagList) - 3 ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Download -->
        <?php if (!empty($asset['file_url'])): ?>
        <a href="<?= htmlspecialchars($asset['file_url'], ENT_QUOTES, 'UTF-8') ?>"
           class="alib__download"
           download
           title="Descargar"
           @click.stop>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
        </a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<nav class="alib__pagination">
    <?php if ($page > 1): ?>
    <button class="btn btn--ghost btn--sm"
            hx-get="/api/partials/asset-library.php?page=<?= $page - 1 ?><?= $qsBase ?>"
            hx-target="#asset-library-root"
            hx-swap="innerHTML">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Anterior
    </button>
    <?php endif; ?>

    <div class="alib__page-numbers">
        <?php
        $start = max(1, $page - 2);
        $end   = min($pages, $page + 2);
        if ($start > 1): ?>
        <button class="btn btn--ghost btn--sm"
                hx-get="/api/partials/asset-library.php?page=1<?= $qsBase ?>"
                hx-target="#asset-library-root"
                hx-swap="innerHTML">1</button>
        <?php if ($start > 2): ?><span class="alib__ellipsis">...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $start; $p <= $end; $p++): ?>
        <button class="btn btn--ghost btn--sm <?= $p === $page ? 'alib__page--active' : '' ?>"
                hx-get="/api/partials/asset-library.php?page=<?= $p ?><?= $qsBase ?>"
                hx-target="#asset-library-root"
                hx-swap="innerHTML"><?= $p ?></button>
        <?php endfor; ?>

        <?php if ($end < $pages): ?>
        <?php if ($end < $pages - 1): ?><span class="alib__ellipsis">...</span><?php endif; ?>
        <button class="btn btn--ghost btn--sm"
                hx-get="/api/partials/asset-library.php?page=<?= $pages ?><?= $qsBase ?>"
                hx-target="#asset-library-root"
                hx-swap="innerHTML"><?= $pages ?></button>
        <?php endif; ?>
    </div>

    <?php if ($page < $pages): ?>
    <button class="btn btn--ghost btn--sm"
            hx-get="/api/partials/asset-library.php?page=<?= $page + 1 ?><?= $qsBase ?>"
            hx-target="#asset-library-root"
            hx-swap="innerHTML">
        Siguiente
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
    <?php endif; ?>
</nav>
<?php endif; ?>

<?php endif; ?>

<style>
/* ── Asset Library ──────────────────────────────────────────────────────── */
.alib__toolbar {
    display: flex;
    align-items: center;
    gap: var(--pw-space-3);
    flex-wrap: wrap;
    margin-bottom: var(--pw-space-3);
}

.alib__search-wrap {
    position: relative;
    flex: 1;
    min-width: 200px;
}

.alib__search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--pw-text-muted);
    pointer-events: none;
}

.alib__search {
    width: 100%;
    background: var(--pw-bg);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-md);
    padding: var(--pw-space-2) var(--pw-space-3) var(--pw-space-2) 32px;
    font-size: 0.85rem;
    color: var(--pw-text);
    transition: border-color 0.15s;
    font-family: inherit;
}

.alib__search:focus {
    outline: none;
    border-color: var(--pw-accent, #00D9FF);
    box-shadow: 0 0 0 2px rgba(0, 217, 255, 0.15);
}

.alib__filters {
    display: flex;
    gap: var(--pw-space-2);
    flex-wrap: wrap;
}

.alib__select {
    background: var(--pw-bg);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-md);
    padding: var(--pw-space-1) var(--pw-space-2);
    font-size: 0.82rem;
    color: var(--pw-text);
    min-width: 140px;
    font-family: inherit;
    cursor: pointer;
}

.alib__select:focus {
    outline: none;
    border-color: var(--pw-accent, #00D9FF);
}

.alib__count {
    font-size: 0.78rem;
    color: var(--pw-text-muted);
    margin-bottom: var(--pw-space-3);
}

/* ── Empty state ────────────────────────────────────────────────────────── */
.alib__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--pw-space-3);
    padding: var(--pw-space-6) var(--pw-space-4);
    color: var(--pw-text-muted);
    text-align: center;
}

.alib__empty p {
    font-size: 0.9rem;
    margin: 0;
}

/* ── Grid ───────────────────────────────────────────────────────────────── */
.alib__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: var(--pw-space-3);
    margin-bottom: var(--pw-space-4);
}

/* ── Card ───────────────────────────────────────────────────────────────── */
.alib__card {
    background: var(--pw-panel);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-lg);
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
    position: relative;
    display: flex;
    flex-direction: column;
}

.alib__card:hover {
    border-color: var(--pw-accent, #00D9FF);
    box-shadow: 0 4px 20px rgba(0, 217, 255, 0.1);
    transform: translateY(-2px);
}

/* ── Thumbnail ──────────────────────────────────────────────────────────── */
.alib__thumb {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 10;
    background: var(--pw-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.alib__thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.alib__thumb-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--type-color, var(--pw-text-muted));
    opacity: 0.5;
}

.alib__thumb-icon--video {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: rgba(0, 217, 255, 0.1);
    border: 2px solid rgba(0, 217, 255, 0.3);
    color: var(--pw-accent, #00D9FF);
    opacity: 1;
}

.alib__type-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 2px 8px;
    border-radius: var(--pw-radius-sm);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--pw-black, #0C0C0F);
}

/* ── Card body ──────────────────────────────────────────────────────────── */
.alib__card-body {
    padding: var(--pw-space-2) var(--pw-space-3);
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.alib__card-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--pw-text);
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.alib__card-project {
    font-size: 0.72rem;
    color: var(--pw-accent, #00D9FF);
    font-weight: 500;
}

.alib__card-client {
    font-size: 0.7rem;
    color: var(--pw-text-muted);
}

/* ── Tags ───────────────────────────────────────────────────────────────── */
.alib__card-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    margin-top: 4px;
}

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

.alib__tag--more {
    background: var(--pw-bg);
    color: var(--pw-text-muted);
    border-color: var(--pw-border);
}

/* ── Download button ────────────────────────────────────────────────────── */
.alib__download {
    position: absolute;
    bottom: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--pw-bg);
    border: 1px solid var(--pw-border);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pw-text-muted);
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    text-decoration: none;
    z-index: 2;
}

.alib__download:hover {
    background: var(--pw-accent, #00D9FF);
    color: var(--pw-black, #0C0C0F);
    border-color: var(--pw-accent, #00D9FF);
}

/* ── Pagination ─────────────────────────────────────────────────────────── */
.alib__pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--pw-space-2);
    flex-wrap: wrap;
}

.alib__page-numbers {
    display: flex;
    align-items: center;
    gap: 2px;
}

.alib__page--active {
    background: var(--pw-accent, #00D9FF) !important;
    color: var(--pw-black, #0C0C0F) !important;
    font-weight: 700 !important;
}

.alib__ellipsis {
    color: var(--pw-text-muted);
    padding: 0 4px;
    font-size: 0.8rem;
}

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .alib__toolbar { flex-direction: column; align-items: stretch; }
    .alib__filters { flex-direction: column; }
    .alib__select  { min-width: 100%; }
    .alib__grid    { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: var(--pw-space-2); }
}

@media (max-width: 480px) {
    .alib__grid { grid-template-columns: repeat(2, 1fr); }
    .alib__card-title { font-size: 0.78rem; }
}
</style>
