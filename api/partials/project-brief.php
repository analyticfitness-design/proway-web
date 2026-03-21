<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

use ProWay\Domain\Brief\BriefService;
use ProWay\Domain\Brief\MySQLBriefRepository;

$briefRepo    = new MySQLBriefRepository($pdo);
$briefService = new BriefService($briefRepo);

$projectId = (int) ($_GET['project_id'] ?? 0);

if ($projectId === 0) {
    echo '<div class="alert alert--error">project_id es requerido.</div>';
    exit;
}

try {
    $brief = $briefService->getByProject($projectId);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar el brief.</div>';
    exit;
}

$isAdmin  = $currentUser->type === 'admin';
$isClient = $currentUser->type === 'client';
$status   = $brief['status'] ?? 'draft';

// Clients cannot edit after submission; admins always can
$readOnly = $isClient && $status === 'submitted';

$statusBadge = match ($status) {
    'submitted' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(0,217,255,0.15); color: var(--pw-accent, #00D9FF);">Enviado</span>',
    'approved'  => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(0,255,135,0.15); color: var(--pw-accent-2, #00FF87);">Aprobado</span>',
    default     => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; background: rgba(255,255,255,0.08); color: var(--pw-text-muted);">Borrador</span>',
};

// Tone options
$tones = ['Profesional', 'Casual', 'Energético', 'Informativo'];
$currentTone = $brief['tone'] ?? '';

// Helper
$val = fn(string $key) => htmlspecialchars((string) ($brief[$key] ?? ''), ENT_QUOTES, 'UTF-8');
$disabled = $readOnly ? 'disabled' : '';
?>

<div id="project-brief" class="card" style="margin-top: var(--pw-space-4);">
    <div class="card__header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card__title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -3px;">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Brief Creativo
        </h3>
        <?= $statusBadge ?>
    </div>

    <?php if ($readOnly): ?>
    <!-- Read-only view for clients after submission -->
    <div style="padding: var(--pw-space-4); display: flex; flex-direction: column; gap: var(--pw-space-3);">
        <p style="color: var(--pw-accent, #00D9FF); font-size: 0.85rem; margin-bottom: var(--pw-space-2);">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px;">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            Brief enviado el <?= date('d/m/Y H:i', strtotime($brief['submitted_at'] ?? '')) ?>. Contacta al equipo para modificaciones.
        </p>

        <?php if ($brief['objective']): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Objetivo</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= nl2br($val('objective')) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($brief['target_audience']): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Audiencia Objetivo</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= nl2br($val('target_audience')) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($currentTone): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Tono</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= htmlspecialchars($currentTone, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php endif; ?>

        <?php if ($brief['key_messages']): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Mensajes Clave</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= nl2br($val('key_messages')) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($brief['references_urls']): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Referencias</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= nl2br($val('references_urls')) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($brief['filming_date']): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Fecha de Grabación</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= date('d/m/Y', strtotime($brief['filming_date'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($brief['location']): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Locación</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= $val('location') ?></p>
        </div>
        <?php endif; ?>

        <?php if ($brief['talent_notes']): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Notas de Talento</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= nl2br($val('talent_notes')) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($brief['special_reqs']): ?>
        <div>
            <label style="color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Requisitos Especiales</label>
            <p style="color: var(--pw-text); margin-top: 4px;"><?= nl2br($val('special_reqs')) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Editable form -->
    <form id="brief-form"
          hx-put="/api/v1/projects/<?= $projectId ?>/brief"
          hx-target="#project-brief"
          hx-swap="outerHTML"
          hx-indicator="#brief-spinner"
          style="padding: var(--pw-space-4); display: flex; flex-direction: column; gap: var(--pw-space-3);">

        <!-- Objective -->
        <div>
            <label for="brief-objective" style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                Objetivo del Contenido
            </label>
            <textarea id="brief-objective"
                      name="objective"
                      class="form-input"
                      rows="3"
                      placeholder="¿Qué quieres lograr con este contenido?"
                      <?= $disabled ?>><?= $val('objective') ?></textarea>
        </div>

        <!-- Target Audience -->
        <div>
            <label for="brief-audience" style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                Audiencia Objetivo
            </label>
            <textarea id="brief-audience"
                      name="target_audience"
                      class="form-input"
                      rows="2"
                      placeholder="¿A quién va dirigido? Edad, intereses, ubicación..."
                      <?= $disabled ?>><?= $val('target_audience') ?></textarea>
        </div>

        <!-- Tone (radio cards) -->
        <div>
            <label style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                Tono
            </label>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: var(--pw-space-2);">
                <?php foreach ($tones as $tone): ?>
                <label style="display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 8px; border: 1px solid <?= $currentTone === $tone ? 'var(--pw-accent, #00D9FF)' : 'rgba(255,255,255,0.08)' ?>; background: <?= $currentTone === $tone ? 'rgba(0,217,255,0.08)' : 'rgba(255,255,255,0.02)' ?>; cursor: pointer; transition: border-color 0.2s, background 0.2s;">
                    <input type="radio"
                           name="tone"
                           value="<?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?>"
                           <?= $currentTone === $tone ? 'checked' : '' ?>
                           <?= $disabled ?>
                           style="accent-color: var(--pw-accent, #00D9FF);">
                    <span style="color: var(--pw-text); font-size: 0.9rem;"><?= htmlspecialchars($tone, ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Key Messages -->
        <div>
            <label for="brief-messages" style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                Mensajes Clave
            </label>
            <textarea id="brief-messages"
                      name="key_messages"
                      class="form-input"
                      rows="3"
                      placeholder="¿Qué mensajes o frases quieres incluir?"
                      <?= $disabled ?>><?= $val('key_messages') ?></textarea>
        </div>

        <!-- References URLs -->
        <div>
            <label for="brief-refs" style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                Referencias (URLs)
            </label>
            <textarea id="brief-refs"
                      name="references_urls"
                      class="form-input"
                      rows="2"
                      placeholder="Links de inspiración, videos, posts de referencia..."
                      <?= $disabled ?>><?= $val('references_urls') ?></textarea>
        </div>

        <!-- Filming Date + Location (side by side) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--pw-space-3);">
            <div>
                <label for="brief-date" style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                    Fecha de Grabación
                </label>
                <input type="date"
                       id="brief-date"
                       name="filming_date"
                       class="form-input"
                       value="<?= $val('filming_date') ?>"
                       <?= $disabled ?>>
            </div>
            <div>
                <label for="brief-location" style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                    Locación
                </label>
                <input type="text"
                       id="brief-location"
                       name="location"
                       class="form-input"
                       placeholder="Dirección o lugar"
                       value="<?= $val('location') ?>"
                       <?= $disabled ?>>
            </div>
        </div>

        <!-- Talent Notes -->
        <div>
            <label for="brief-talent" style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                Notas de Talento
            </label>
            <textarea id="brief-talent"
                      name="talent_notes"
                      class="form-input"
                      rows="2"
                      placeholder="¿Quién aparecerá? Vestimenta, indicaciones..."
                      <?= $disabled ?>><?= $val('talent_notes') ?></textarea>
        </div>

        <!-- Special Requirements -->
        <div>
            <label for="brief-special" style="display: block; color: var(--pw-text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                Requisitos Especiales
            </label>
            <textarea id="brief-special"
                      name="special_reqs"
                      class="form-input"
                      rows="2"
                      placeholder="Logotipos, música, restricciones, formatos..."
                      <?= $disabled ?>><?= $val('special_reqs') ?></textarea>
        </div>

        <!-- Actions -->
        <div style="display: flex; align-items: center; gap: var(--pw-space-2); padding-top: var(--pw-space-2); border-top: 1px solid rgba(255,255,255,0.06);">
            <button type="submit"
                    class="btn btn--sm"
                    style="background: rgba(255,255,255,0.08); color: var(--pw-text); font-weight: 600; border: 1px solid rgba(255,255,255,0.12);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Guardar Borrador
            </button>

            <?php if ($status !== 'approved'): ?>
            <button type="button"
                    class="btn btn--sm"
                    style="background: var(--pw-accent, #00D9FF); color: #000; font-weight: 600;"
                    hx-post="/api/v1/projects/<?= $projectId ?>/brief/submit"
                    hx-target="#project-brief"
                    hx-swap="outerHTML"
                    hx-confirm="¿Enviar el brief? Una vez enviado no podrás editarlo.">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
                Enviar Brief
            </button>
            <?php endif; ?>

            <span id="brief-spinner" class="htmx-indicator" style="color: var(--pw-accent);">Guardando...</span>
        </div>
    </form>
    <?php endif; ?>
</div>
