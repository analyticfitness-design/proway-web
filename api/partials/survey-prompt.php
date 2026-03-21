<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

// Only meaningful for clients
if ($currentUser->type !== 'client') {
    exit;
}

try {
    $surveys = $surveyService->getPendingForClient($currentUser->id);
} catch (Throwable $e) {
    exit; // Silently fail — survey banner is non-critical
}

if (empty($surveys)) {
    exit; // Nothing to show
}

$survey = $surveys[0];
$surveyId = (int) $survey['id'];
$type     = $survey['type'] === 'csat' ? 'csat' : 'nps';
$maxScore = $type === 'csat' ? 5 : 10;
?>
<div id="survey-banner"
     style="
         background: linear-gradient(135deg, rgba(0,212,255,0.08) 0%, rgba(148,0,211,0.08) 100%);
         border: 1px solid rgba(0,212,255,0.2);
         border-radius: 12px;
         padding: var(--pw-space-4, 1.5rem);
         margin-bottom: var(--pw-space-4, 1.5rem);
         position: relative;
         box-shadow: 0 0 24px rgba(0,212,255,0.06);
     ">

    <!-- Dismiss button -->
    <button
        onclick="document.getElementById('survey-banner').style.display='none'"
        title="Cerrar"
        style="
            position: absolute;
            top: 12px;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--pw-text-muted, #888);
            line-height: 1;
            padding: 4px;
        "
        aria-label="Cerrar encuesta">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>

    <!-- Header -->
    <div style="display: flex; align-items: center; gap: var(--pw-space-2, 0.75rem); margin-bottom: var(--pw-space-3, 1rem);">
        <span style="
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0,212,255,0.15);
            color: var(--pw-accent, #00D4FF);
            flex-shrink: 0;
        ">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </span>
        <div>
            <p style="font-weight: 600; color: var(--pw-text, #fff); margin: 0; font-size: 0.95rem;">
                <?= $type === 'nps'
                    ? '¿Recomendarías ProWay Lab a otros?'
                    : '¿Qué tan satisfecho estás con nuestro servicio?' ?>
            </p>
            <p style="color: var(--pw-text-muted, #888); font-size: 0.82rem; margin: 2px 0 0;">
                <?= $type === 'nps'
                    ? 'En una escala del 0 al 10, donde 0 = nada probable y 10 = totalmente seguro'
                    : 'En una escala del 1 al 5, donde 1 = muy insatisfecho y 5 = muy satisfecho' ?>
            </p>
        </div>
    </div>

    <!-- Survey form -->
    <form id="survey-form-<?= $surveyId ?>"
          hx-post="/api/v1/surveys/<?= $surveyId ?>/respond"
          hx-target="#survey-banner"
          hx-swap="outerHTML"
          hx-indicator="#survey-spinner-<?= $surveyId ?>">

        <!-- NPS / CSAT score circles -->
        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: var(--pw-space-3, 1rem);" role="radiogroup" aria-label="Puntaje">
            <?php for ($i = 0; $i <= $maxScore; $i++):
                // Color gradient: red (0-6) → yellow (7-8) → green (9-10) for NPS
                // Color gradient: red (1) → yellow (3) → green (5) for CSAT
                if ($type === 'nps') {
                    if ($i <= 6)      $color = '#E31E24'; // red — detractor
                    elseif ($i <= 8)  $color = '#FFC107'; // yellow — passive
                    else              $color = '#00FF87'; // green — promoter
                } else {
                    // CSAT: skip 0
                    if ($i === 0) continue;
                    if ($i <= 2)      $color = '#E31E24';
                    elseif ($i === 3) $color = '#FFC107';
                    else              $color = '#00FF87';
                }
            ?>
            <label style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: 2px solid <?= $color ?>;
                color: <?= $color ?>;
                font-weight: 700;
                font-size: 0.9rem;
                cursor: pointer;
                transition: background 0.15s, color 0.15s;
                user-select: none;
            "
            onmouseover="this.style.background='<?= $color ?>'; this.style.color='#0D0D1A';"
            onmouseout="
                const inp = document.querySelector('input[name=score][value=\'<?= $i ?>\']:checked');
                if (!inp) { this.style.background='transparent'; this.style.color='<?= $color ?>'; }
            "
            title="Puntaje <?= $i ?>">
                <input type="radio"
                       name="score"
                       value="<?= $i ?>"
                       style="position: absolute; opacity: 0; width: 0; height: 0;"
                       onchange="
                           // Deselect all circles
                           document.querySelectorAll('[data-score-label]').forEach(function(el) {
                               el.style.background = 'transparent';
                               el.style.color = el.dataset.color;
                           });
                           // Highlight selected
                           this.parentElement.style.background = this.parentElement.dataset.color;
                           this.parentElement.style.color = '#0D0D1A';
                       ">
                <?= $i ?>
            </label>
            <?php endfor; ?>
        </div>

        <!-- Legend -->
        <?php if ($type === 'nps'): ?>
        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--pw-text-muted, #888); margin-bottom: var(--pw-space-3, 1rem);">
            <span style="color: #E31E24;">0 — Nada probable</span>
            <span style="color: #00FF87;">10 — Totalmente seguro</span>
        </div>
        <?php else: ?>
        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--pw-text-muted, #888); margin-bottom: var(--pw-space-3, 1rem);">
            <span style="color: #E31E24;">1 — Muy insatisfecho</span>
            <span style="color: #00FF87;">5 — Muy satisfecho</span>
        </div>
        <?php endif; ?>

        <!-- Optional comment -->
        <textarea name="comment"
                  rows="2"
                  placeholder="Comentario adicional (opcional)..."
                  style="
                      width: 100%;
                      background: rgba(255,255,255,0.04);
                      border: 1px solid rgba(255,255,255,0.1);
                      border-radius: 8px;
                      color: var(--pw-text, #fff);
                      font-size: 0.85rem;
                      padding: 8px 12px;
                      resize: vertical;
                      margin-bottom: var(--pw-space-3, 1rem);
                      box-sizing: border-box;
                  "
                  onfocus="this.style.borderColor='rgba(0,212,255,0.4)'"
                  onblur="this.style.borderColor='rgba(255,255,255,0.1)'"></textarea>

        <!-- Actions -->
        <div style="display: flex; align-items: center; gap: var(--pw-space-2, 0.75rem);">
            <button type="submit"
                    style="
                        background: linear-gradient(135deg, var(--pw-accent, #00D4FF), #9400D3);
                        color: #fff;
                        border: none;
                        border-radius: 8px;
                        padding: 8px 20px;
                        font-weight: 600;
                        font-size: 0.875rem;
                        cursor: pointer;
                        box-shadow: 0 0 16px rgba(0,212,255,0.25);
                        transition: opacity 0.15s;
                    "
                    onmouseover="this.style.opacity='0.85'"
                    onmouseout="this.style.opacity='1'">
                Enviar respuesta
            </button>
            <span id="survey-spinner-<?= $surveyId ?>"
                  class="htmx-indicator"
                  style="color: var(--pw-accent, #00D4FF); font-size: 0.85rem;">
                Enviando...
            </span>
        </div>
    </form>
</div>
