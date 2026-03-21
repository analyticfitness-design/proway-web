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

use ProWay\Domain\AI\ContentSuggestionService;
use ProWay\Domain\AI\MySQLSuggestionRepository;

try {
    $suggestionService = new ContentSuggestionService(new MySQLSuggestionRepository($pdo));

    // Load clients for the selector
    $clientsStmt = $pdo->query('SELECT id, name, company FROM clients ORDER BY name ASC');
    $clients     = $clientsStmt->fetchAll();

    // If client_id is set via query, load their history
    $selectedClientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
    $history = [];
    if ($selectedClientId > 0) {
        $history = $suggestionService->listForClient($selectedClientId, 10);
    }
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar el modulo de IA: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

$platforms = ['Instagram', 'TikTok', 'YouTube'];
$months    = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$currentMonth = $months[(int) date('n') - 1] . ' ' . date('Y');
?>

<div class="ai-suggestions" x-data="aiSuggestions()">

    <!-- Generator form -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--pw-space-4); margin-bottom: var(--pw-space-5);">

        <!-- Content Ideas Generator -->
        <div style="background: var(--pw-panel); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-lg); padding: var(--pw-space-4);">
            <div style="display:flex; align-items:center; gap: var(--pw-space-2); margin-bottom: var(--pw-space-3);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--pw-accent)" stroke-width="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
                </svg>
                <h3 style="margin:0; font-size: 1rem; color: var(--pw-text);">Generar Ideas de Contenido</h3>
            </div>

            <form id="ai-suggestions-form"
                  hx-post="/api/v1/admin/ai/suggestions"
                  hx-target="#ai-result"
                  hx-swap="innerHTML"
                  hx-indicator="#ai-spinner"
                  hx-headers='{"Authorization": "Bearer " + document.cookie.match(/pw_access=([^;]+)/)?.[1]}'
                  hx-ext="json-enc"
                  @htmx:after-request.window="if(event.detail.elt.id === 'ai-suggestions-form') handleSuggestionsResponse(event)">

                <div style="display:flex; flex-direction:column; gap: var(--pw-space-3);">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--pw-text-muted); display:block; margin-bottom: 4px;">Cliente</label>
                        <select name="client_id" required
                                style="width:100%; padding: 8px 12px; background: var(--pw-bg); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-md); color: var(--pw-text); font-size: 0.85rem;">
                            <option value="">Seleccionar cliente...</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $selectedClientId === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?><?= $c['company'] ? ' — ' . htmlspecialchars($c['company']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; color: var(--pw-text-muted); display:block; margin-bottom: 4px;">Plataforma</label>
                        <select name="platform" required
                                style="width:100%; padding: 8px 12px; background: var(--pw-bg); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-md); color: var(--pw-text); font-size: 0.85rem;">
                            <?php foreach ($platforms as $p): ?>
                            <option value="<?= $p ?>"><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; color: var(--pw-text-muted); display:block; margin-bottom: 4px;">Nicho</label>
                        <input type="text" name="niche" value="fitness" placeholder="ej: fitness, yoga, CrossFit"
                               style="width:100%; padding: 8px 12px; background: var(--pw-bg); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-md); color: var(--pw-text); font-size: 0.85rem;">
                    </div>

                    <button type="submit" class="btn btn--primary"
                            style="width:100%; display:flex; align-items:center; justify-content:center; gap: var(--pw-space-2); margin-top: var(--pw-space-2);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                        </svg>
                        Generar Ideas
                        <span id="ai-spinner" class="htmx-indicator" style="display:inline-block;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 style="animation: pw-spin 0.8s linear infinite;">
                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Trend Analysis Generator -->
        <div style="background: var(--pw-panel); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-lg); padding: var(--pw-space-4);">
            <div style="display:flex; align-items:center; gap: var(--pw-space-2); margin-bottom: var(--pw-space-3);">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--pw-accent)" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
                <h3 style="margin:0; font-size: 1rem; color: var(--pw-text);">Analisis de Tendencias</h3>
            </div>

            <form id="ai-trends-form"
                  hx-post="/api/v1/admin/ai/trend-analysis"
                  hx-target="#ai-result"
                  hx-swap="innerHTML"
                  hx-indicator="#ai-trend-spinner"
                  hx-headers='{"Authorization": "Bearer " + document.cookie.match(/pw_access=([^;]+)/)?.[1]}'
                  hx-ext="json-enc"
                  @htmx:after-request.window="if(event.detail.elt.id === 'ai-trends-form') handleSuggestionsResponse(event)">

                <div style="display:flex; flex-direction:column; gap: var(--pw-space-3);">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--pw-text-muted); display:block; margin-bottom: 4px;">Cliente</label>
                        <select name="client_id" required
                                style="width:100%; padding: 8px 12px; background: var(--pw-bg); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-md); color: var(--pw-text); font-size: 0.85rem;">
                            <option value="">Seleccionar cliente...</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $selectedClientId === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?><?= $c['company'] ? ' — ' . htmlspecialchars($c['company']) : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; color: var(--pw-text-muted); display:block; margin-bottom: 4px;">Plataforma</label>
                        <select name="platform" required
                                style="width:100%; padding: 8px 12px; background: var(--pw-bg); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-md); color: var(--pw-text); font-size: 0.85rem;">
                            <?php foreach ($platforms as $p): ?>
                            <option value="<?= $p ?>"><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; color: var(--pw-text-muted); display:block; margin-bottom: 4px;">Mes</label>
                        <input type="text" name="month" value="<?= htmlspecialchars($currentMonth) ?>" placeholder="ej: Marzo 2026"
                               style="width:100%; padding: 8px 12px; background: var(--pw-bg); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-md); color: var(--pw-text); font-size: 0.85rem;">
                    </div>

                    <button type="submit" class="btn btn--primary"
                            style="width:100%; display:flex; align-items:center; justify-content:center; gap: var(--pw-space-2); margin-top: var(--pw-space-2);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                        Analizar Tendencias
                        <span id="ai-trend-spinner" class="htmx-indicator" style="display:inline-block;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 style="animation: pw-spin 0.8s linear infinite;">
                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results area -->
    <div id="ai-result" style="margin-bottom: var(--pw-space-5);"></div>

    <!-- History section -->
    <?php if ($selectedClientId > 0 && count($history) > 0): ?>
    <div style="margin-top: var(--pw-space-4);">
        <h3 style="font-size: 0.85rem; color: var(--pw-text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--pw-space-3);">
            Historial de Generaciones
        </h3>
        <div style="display:flex; flex-direction:column; gap: var(--pw-space-3);">
            <?php foreach ($history as $item):
                $context = json_decode($item['context_json'] ?? '{}', true);
                $promptParts = explode(':', $item['prompt_type']);
                $type = $promptParts[0] ?? 'unknown';
                $typeBadge = $type === 'content_suggestions' ? 'Ideas' : ($type === 'trend_analysis' ? 'Tendencias' : ucfirst($type));
                $platform = $context['platform'] ?? '';
            ?>
            <details style="background: var(--pw-panel); border: 1px solid var(--pw-border); border-radius: var(--pw-radius-lg); overflow:hidden;">
                <summary style="padding: var(--pw-space-3); cursor: pointer; display:flex; align-items:center; gap: var(--pw-space-2); font-size: 0.85rem; color: var(--pw-text);">
                    <span style="background: var(--pw-accent); color: #000; padding: 2px 8px; border-radius: var(--pw-radius-sm); font-size: 0.7rem; font-weight: 600;">
                        <?= htmlspecialchars($typeBadge) ?>
                    </span>
                    <span style="color: var(--pw-text-muted);"><?= htmlspecialchars($platform) ?></span>
                    <span style="margin-left:auto; color: var(--pw-text-muted); font-size: 0.75rem;">
                        <?= htmlspecialchars($item['created_at']) ?> &middot; <?= (int) $item['tokens_used'] ?> tokens
                    </span>
                </summary>
                <div style="padding: 0 var(--pw-space-3) var(--pw-space-3); font-size: 0.85rem; color: var(--pw-text); white-space: pre-wrap; line-height: 1.6;">
<?= htmlspecialchars($item['result_text'] ?? '') ?>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif ($selectedClientId > 0): ?>
    <div style="text-align:center; padding: var(--pw-space-5); color: var(--pw-text-muted); font-size: 0.85rem;">
        No hay generaciones previas para este cliente. Usa los formularios de arriba para generar ideas.
    </div>
    <?php else: ?>
    <div style="text-align:center; padding: var(--pw-space-5); color: var(--pw-text-muted); font-size: 0.85rem;">
        Selecciona un cliente en el formulario para ver el historial de generaciones.
    </div>
    <?php endif; ?>

</div>

<style>
@keyframes pw-spin { to { transform: rotate(360deg); } }
.htmx-indicator { opacity: 0; transition: opacity 0.2s; pointer-events: none; }
.htmx-request .htmx-indicator,
.htmx-indicator.htmx-request { opacity: 1; }

.ai-suggestions details[open] summary {
    border-bottom: 1px solid var(--pw-border);
}

.ai-result-card {
    background: var(--pw-panel);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-lg);
    padding: var(--pw-space-4);
    margin-bottom: var(--pw-space-3);
}

.ai-result-card__header {
    display: flex;
    align-items: center;
    gap: var(--pw-space-2);
    margin-bottom: var(--pw-space-3);
}

.ai-result-card__badge {
    background: var(--pw-accent);
    color: #000;
    padding: 2px 10px;
    border-radius: var(--pw-radius-sm);
    font-size: 0.7rem;
    font-weight: 600;
}

.ai-result-card__cached {
    background: rgba(0, 255, 135, 0.15);
    color: #00FF87;
    padding: 2px 8px;
    border-radius: var(--pw-radius-sm);
    font-size: 0.65rem;
    font-weight: 500;
}

.ai-result-card__content {
    font-size: 0.85rem;
    color: var(--pw-text);
    white-space: pre-wrap;
    line-height: 1.7;
}

.ai-result-card__meta {
    display: flex;
    align-items: center;
    gap: var(--pw-space-3);
    margin-top: var(--pw-space-3);
    padding-top: var(--pw-space-2);
    border-top: 1px solid var(--pw-border);
    font-size: 0.7rem;
    color: var(--pw-text-muted);
}

@media (max-width: 768px) {
    .ai-suggestions > div:first-child {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
function aiSuggestions() {
    return {};
}

function handleSuggestionsResponse(event) {
    var xhr = event.detail.xhr;
    var resultDiv = document.getElementById('ai-result');
    if (!resultDiv) return;

    try {
        var data = JSON.parse(xhr.responseText);

        if (data.success && data.data) {
            var d = data.data;
            var cachedTag = d.cached
                ? '<span class="ai-result-card__cached">CACHE 24h</span>'
                : '';

            // Build result safely using DOM methods
            var card = document.createElement('div');
            card.className = 'ai-result-card';

            var header = document.createElement('div');
            header.className = 'ai-result-card__header';

            var badge = document.createElement('span');
            badge.className = 'ai-result-card__badge';
            badge.textContent = 'Resultado IA';
            header.appendChild(badge);

            if (d.cached) {
                var cache = document.createElement('span');
                cache.className = 'ai-result-card__cached';
                cache.textContent = 'CACHE 24h';
                header.appendChild(cache);
            }

            card.appendChild(header);

            var content = document.createElement('div');
            content.className = 'ai-result-card__content';
            content.textContent = d.result_text || '';
            card.appendChild(content);

            var meta = document.createElement('div');
            meta.className = 'ai-result-card__meta';

            var tokensSpan = document.createElement('span');
            tokensSpan.textContent = 'Tokens: ' + d.tokens_used;
            meta.appendChild(tokensSpan);

            var dateSpan = document.createElement('span');
            dateSpan.textContent = 'Generado: ' + d.created_at;
            meta.appendChild(dateSpan);

            if (d.cached) {
                var cacheNote = document.createElement('span');
                cacheNote.textContent = 'Resultado en cache (expira en 24h)';
                meta.appendChild(cacheNote);
            }

            card.appendChild(meta);

            resultDiv.replaceChildren(card);
        } else if (data.error) {
            var errDiv = document.createElement('div');
            errDiv.className = 'alert alert--error';
            errDiv.textContent = data.error.message;
            resultDiv.replaceChildren(errDiv);
        }
    } catch (e) {
        var errDiv = document.createElement('div');
        errDiv.className = 'alert alert--error';
        errDiv.textContent = 'Error al procesar la respuesta de la IA.';
        resultDiv.replaceChildren(errDiv);
    }
}
</script>
