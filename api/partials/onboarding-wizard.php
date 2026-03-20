<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

// Fetch current onboarding profile
$stmt = $pdo->prepare(
    'SELECT brand_name, brand_colors, logo_url, social_accounts,
            content_prefs, goals, onboarding_done
     FROM client_profiles WHERE client_id = ?'
);
$stmt->execute([$currentUser->id]);
$profile = $stmt->fetch() ?: [];

$brandName    = $profile['brand_name'] ?? '';
$brandColors  = json_decode($profile['brand_colors'] ?? '[]', true) ?: [];
$logoUrl      = $profile['logo_url'] ?? '';
$social       = json_decode($profile['social_accounts'] ?? '{}', true) ?: [];
$prefs        = json_decode($profile['content_prefs'] ?? '{}', true) ?: [];
$goals        = $profile['goals'] ?? '';
$done         = (int) ($profile['onboarding_done'] ?? 0);

// Pre-encode for Alpine
$brandColorsJson = htmlspecialchars(json_encode($brandColors), ENT_QUOTES, 'UTF-8');
$socialJson      = htmlspecialchars(json_encode($social), ENT_QUOTES, 'UTF-8');
$prefsJson       = htmlspecialchars(json_encode($prefs), ENT_QUOTES, 'UTF-8');
?>

<div x-data="onboardingWizard()" x-init="init()" class="onboarding-wizard">

    <!-- ── Step indicator ────────────────────────────────────────── -->
    <div class="wizard-steps">
        <template x-for="s in maxSteps" :key="s">
            <div class="wizard-step"
                 :class="{ 'wizard-step--active': step === s, 'wizard-step--done': step > s }">
                <div class="wizard-step__circle">
                    <template x-if="step > s">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </template>
                    <template x-if="step <= s">
                        <span x-text="s"></span>
                    </template>
                </div>
                <span class="wizard-step__label"
                      x-text="['', 'Marca', 'Redes', 'Contenido', 'Metas'][s]"></span>
            </div>
        </template>
    </div>

    <!-- Progress bar -->
    <div class="wizard-progress">
        <div class="wizard-progress__bar"
             :style="'width: ' + ((step - 1) / (maxSteps - 1) * 100) + '%'"></div>
    </div>

    <!-- ── Step 1: Brand Info ────────────────────────────────────── -->
    <div x-show="step === 1" x-transition:enter class="wizard-panel">
        <h2 class="wizard-panel__title">Tu Marca</h2>
        <p class="wizard-panel__desc">Cuéntanos sobre tu marca para personalizar tu experiencia.</p>

        <div class="form-group">
            <label class="form-label" for="ob-brand-name">Nombre de marca</label>
            <input class="form-input" type="text" id="ob-brand-name"
                   x-model="formData.brand_name"
                   placeholder="Ej: FitPro, GymLife..."
                   maxlength="100">
        </div>

        <div class="form-group">
            <label class="form-label">Colores de marca</label>
            <p style="color: var(--pw-text-muted); font-size: var(--pw-text-xs); margin-bottom: var(--pw-space-2);">
                Agrega hasta 5 colores representativos de tu marca.
            </p>
            <div style="display: flex; align-items: center; gap: var(--pw-space-3); flex-wrap: wrap;">
                <template x-for="(color, idx) in formData.brand_colors" :key="idx">
                    <div style="position: relative;">
                        <input type="color" :value="color"
                               @input="formData.brand_colors[idx] = $event.target.value"
                               style="width: 48px; height: 48px; border: 2px solid var(--pw-border); border-radius: var(--pw-radius); cursor: pointer; background: transparent; padding: 2px;">
                        <button type="button"
                                @click="formData.brand_colors.splice(idx, 1)"
                                style="position: absolute; top: -6px; right: -6px; width: 18px; height: 18px; border-radius: 50%; background: var(--pw-danger, #ff4757); color: white; border: none; cursor: pointer; font-size: 11px; display: flex; align-items: center; justify-content: center; line-height: 1;"
                                aria-label="Quitar color">&times;</button>
                    </div>
                </template>
                <button type="button"
                        @click="if (formData.brand_colors.length < 5) formData.brand_colors.push('#00D9FF')"
                        x-show="formData.brand_colors.length < 5"
                        class="btn btn--ghost btn--sm"
                        style="height: 48px; width: 48px; border: 2px dashed var(--pw-border); display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="ob-logo-url">URL del logo <span style="color: var(--pw-text-muted); font-weight: normal;">(opcional)</span></label>
            <input class="form-input" type="url" id="ob-logo-url"
                   x-model="formData.logo_url"
                   placeholder="https://ejemplo.com/logo.png">
        </div>

        <div class="form-group">
            <label class="form-label" for="ob-style-desc">Descripción del estilo visual <span style="color: var(--pw-text-muted); font-weight: normal;">(opcional)</span></label>
            <textarea class="form-input" id="ob-style-desc"
                      x-model="formData.style_description"
                      rows="3"
                      placeholder="Ej: Minimalista con tonos oscuros, tipografía moderna, estilo deportivo..."
                      style="resize: vertical; min-height: 80px;"></textarea>
        </div>
    </div>

    <!-- ── Step 2: Social Accounts ───────────────────────────────── -->
    <div x-show="step === 2" x-transition:enter class="wizard-panel">
        <h2 class="wizard-panel__title">Redes Sociales</h2>
        <p class="wizard-panel__desc">Conecta tus cuentas para que podamos optimizar tu contenido.</p>

        <div class="form-group">
            <label class="form-label" for="ob-instagram">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;">
                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                </svg>
                Instagram
            </label>
            <input class="form-input" type="text" id="ob-instagram"
                   x-model="formData.social.instagram"
                   placeholder="@tuusuario">
        </div>

        <div class="form-group">
            <label class="form-label" for="ob-tiktok">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;">
                    <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                </svg>
                TikTok
            </label>
            <input class="form-input" type="text" id="ob-tiktok"
                   x-model="formData.social.tiktok"
                   placeholder="@tuusuario">
        </div>

        <div class="form-group">
            <label class="form-label" for="ob-youtube">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;">
                    <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19.13C5.12 19.56 12 19.56 12 19.56s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/>
                </svg>
                YouTube <span style="color: var(--pw-text-muted); font-weight: normal;">(opcional)</span>
            </label>
            <input class="form-input" type="text" id="ob-youtube"
                   x-model="formData.social.youtube"
                   placeholder="Nombre del canal o URL">
        </div>

        <div class="form-group">
            <label class="form-label" for="ob-website">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;">
                    <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
                Sitio web <span style="color: var(--pw-text-muted); font-weight: normal;">(opcional)</span>
            </label>
            <input class="form-input" type="url" id="ob-website"
                   x-model="formData.social.website"
                   placeholder="https://tumarca.com">
        </div>
    </div>

    <!-- ── Step 3: Content Preferences ───────────────────────────── -->
    <div x-show="step === 3" x-transition:enter class="wizard-panel">
        <h2 class="wizard-panel__title">Preferencias de Contenido</h2>
        <p class="wizard-panel__desc">Dinos qué tipo de contenido necesitas y con qué frecuencia.</p>

        <div class="form-group">
            <label class="form-label">Tipos de contenido</label>
            <div class="checkbox-grid">
                <template x-for="type in contentTypes" :key="type">
                    <label class="checkbox-card"
                           :class="{ 'checkbox-card--active': formData.prefs.content_types.includes(type) }">
                        <input type="checkbox"
                               :value="type"
                               :checked="formData.prefs.content_types.includes(type)"
                               @change="toggleContentType(type)"
                               style="display: none;">
                        <span class="checkbox-card__check">
                            <svg x-show="formData.prefs.content_types.includes(type)" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </span>
                        <span x-text="type"></span>
                    </label>
                </template>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Frecuencia de publicación</label>
            <div class="radio-grid">
                <template x-for="freq in frequencies" :key="freq">
                    <label class="radio-card"
                           :class="{ 'radio-card--active': formData.prefs.frequency === freq }">
                        <input type="radio" name="frequency"
                               :value="freq"
                               :checked="formData.prefs.frequency === freq"
                               @change="formData.prefs.frequency = freq"
                               style="display: none;">
                        <span x-text="freq"></span>
                    </label>
                </template>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Tono del contenido</label>
            <div class="radio-grid">
                <template x-for="tone in tones" :key="tone">
                    <label class="radio-card"
                           :class="{ 'radio-card--active': formData.prefs.tone === tone }">
                        <input type="radio" name="tone"
                               :value="tone"
                               :checked="formData.prefs.tone === tone"
                               @change="formData.prefs.tone = tone"
                               style="display: none;">
                        <span x-text="tone"></span>
                    </label>
                </template>
            </div>
        </div>
    </div>

    <!-- ── Step 4: Goals ─────────────────────────────────────────── -->
    <div x-show="step === 4" x-transition:enter class="wizard-panel">
        <h2 class="wizard-panel__title">Tus Metas</h2>
        <p class="wizard-panel__desc">Cuéntanos qué quieres lograr para diseñar la mejor estrategia.</p>

        <div class="form-group">
            <label class="form-label" for="ob-goals">Objetivos principales</label>
            <textarea class="form-input" id="ob-goals"
                      x-model="formData.goals"
                      rows="4"
                      placeholder="Ej: Aumentar seguidores en Instagram a 10K, generar 5 leads semanales, mejorar engagement..."
                      style="resize: vertical; min-height: 100px;"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="ob-metrics">Métricas esperadas <span style="color: var(--pw-text-muted); font-weight: normal;">(opcional)</span></label>
            <textarea class="form-input" id="ob-metrics"
                      x-model="formData.expected_metrics"
                      rows="3"
                      placeholder="Ej: 1000 views por reel, 5% engagement rate, 50 comentarios promedio..."
                      style="resize: vertical; min-height: 80px;"></textarea>
        </div>

        <!-- Completion summary -->
        <div class="wizard-summary" x-show="formData.brand_name || formData.social.instagram || formData.prefs.content_types.length > 0">
            <h3 style="color: var(--pw-accent); font-size: var(--pw-text-sm); margin-bottom: var(--pw-space-3); text-transform: uppercase; letter-spacing: 1px;">Resumen</h3>
            <div style="display: grid; gap: var(--pw-space-2); font-size: var(--pw-text-sm); color: var(--pw-text-muted);">
                <div x-show="formData.brand_name">
                    <strong style="color: var(--pw-text);">Marca:</strong> <span x-text="formData.brand_name"></span>
                </div>
                <div x-show="formData.social.instagram">
                    <strong style="color: var(--pw-text);">Instagram:</strong> <span x-text="formData.social.instagram"></span>
                </div>
                <div x-show="formData.prefs.content_types.length > 0">
                    <strong style="color: var(--pw-text);">Contenido:</strong> <span x-text="formData.prefs.content_types.join(', ')"></span>
                </div>
                <div x-show="formData.prefs.frequency">
                    <strong style="color: var(--pw-text);">Frecuencia:</strong> <span x-text="formData.prefs.frequency"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Navigation ────────────────────────────────────────────── -->
    <div class="wizard-nav">
        <button type="button"
                class="btn btn--ghost"
                @click="prevStep()"
                x-show="step > 1">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            Anterior
        </button>
        <span x-show="step === 1"></span>

        <button type="button"
                class="btn btn--primary"
                @click="nextStep()"
                x-show="step < maxSteps"
                :disabled="saving">
            Siguiente
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
            </svg>
        </button>

        <button type="button"
                class="btn btn--glow"
                @click="finish()"
                x-show="step === maxSteps"
                :disabled="saving"
                style="min-width: 180px;">
            <span x-show="saving" style="display: flex; align-items: center; gap: var(--pw-space-2);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     style="animation: pw-spin 0.8s linear infinite;" aria-hidden="true">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                </svg>
                Guardando...
            </span>
            <span x-show="!saving">
                Completar Onboarding
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 4px;">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </span>
        </button>
    </div>

    <!-- Error / success toast -->
    <div x-show="toast.show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-cloak
         :class="'wizard-toast wizard-toast--' + toast.type"
         x-text="toast.message">
    </div>
</div>

<style>
/* ── Wizard layout ──────────────────────────────────────────────── */
.onboarding-wizard {
    max-width: 680px;
    margin: 0 auto;
    position: relative;
}

/* ── Step indicator ─────────────────────────────────────────────── */
.wizard-steps {
    display: flex;
    justify-content: center;
    gap: var(--pw-space-8);
    margin-bottom: var(--pw-space-4);
}

.wizard-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--pw-space-2);
}

.wizard-step__circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid var(--pw-border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--pw-text-sm);
    font-weight: var(--pw-weight-semibold);
    color: var(--pw-text-muted);
    background: var(--pw-panel);
    transition: all 0.3s ease;
}

.wizard-step--active .wizard-step__circle {
    border-color: var(--pw-accent);
    color: var(--pw-accent);
    box-shadow: 0 0 16px rgba(0, 217, 255, 0.3);
}

.wizard-step--done .wizard-step__circle {
    border-color: var(--pw-accent-2);
    color: var(--pw-accent-2);
    background: rgba(0, 255, 135, 0.1);
}

.wizard-step__label {
    font-size: var(--pw-text-xs);
    color: var(--pw-text-muted);
    transition: color 0.3s;
}

.wizard-step--active .wizard-step__label {
    color: var(--pw-accent);
}

.wizard-step--done .wizard-step__label {
    color: var(--pw-accent-2);
}

/* ── Progress bar ───────────────────────────────────────────────── */
.wizard-progress {
    height: 3px;
    background: var(--pw-border);
    border-radius: 2px;
    margin-bottom: var(--pw-space-8);
    overflow: hidden;
}

.wizard-progress__bar {
    height: 100%;
    background: linear-gradient(90deg, var(--pw-accent), var(--pw-accent-2));
    border-radius: 2px;
    transition: width 0.4s ease;
}

/* ── Panels ─────────────────────────────────────────────────────── */
.wizard-panel {
    background: var(--pw-panel);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-lg, 12px);
    padding: var(--pw-space-8);
    margin-bottom: var(--pw-space-6);
}

.wizard-panel__title {
    font-size: var(--pw-text-xl, 1.25rem);
    font-weight: var(--pw-weight-bold);
    color: var(--pw-text);
    margin-bottom: var(--pw-space-2);
    background: linear-gradient(135deg, var(--pw-accent), var(--pw-accent-2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.wizard-panel__desc {
    color: var(--pw-text-muted);
    font-size: var(--pw-text-sm);
    margin-bottom: var(--pw-space-6);
}

/* ── Checkbox cards ─────────────────────────────────────────────── */
.checkbox-grid,
.radio-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: var(--pw-space-3);
}

.checkbox-card,
.radio-card {
    display: flex;
    align-items: center;
    gap: var(--pw-space-2);
    padding: var(--pw-space-3) var(--pw-space-4);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: var(--pw-text-sm);
    color: var(--pw-text-muted);
    background: transparent;
    user-select: none;
}

.checkbox-card:hover,
.radio-card:hover {
    border-color: rgba(0, 217, 255, 0.4);
    color: var(--pw-text);
}

.checkbox-card--active,
.radio-card--active {
    border-color: var(--pw-accent);
    color: var(--pw-accent);
    background: rgba(0, 217, 255, 0.06);
}

.checkbox-card__check {
    width: 18px;
    height: 18px;
    border: 1px solid var(--pw-border);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s;
}

.checkbox-card--active .checkbox-card__check {
    border-color: var(--pw-accent);
    background: rgba(0, 217, 255, 0.15);
    color: var(--pw-accent);
}

/* ── Summary card ───────────────────────────────────────────────── */
.wizard-summary {
    margin-top: var(--pw-space-6);
    padding: var(--pw-space-5);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius);
    background: rgba(0, 217, 255, 0.03);
}

/* ── Navigation ─────────────────────────────────────────────────── */
.wizard-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ── Glow button (CTA) ─────────────────────────────────────────── */
.btn--glow {
    background: linear-gradient(135deg, var(--pw-accent), var(--pw-accent-2));
    color: var(--pw-black);
    font-weight: var(--pw-weight-bold);
    border: none;
    padding: var(--pw-space-3) var(--pw-space-6);
    border-radius: var(--pw-radius);
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: var(--pw-space-2);
    font-size: var(--pw-text-sm);
}

.btn--glow:hover {
    box-shadow: 0 0 24px rgba(0, 217, 255, 0.4), 0 0 48px rgba(0, 255, 135, 0.2);
    transform: translateY(-1px);
}

.btn--glow:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ── Toast ───────────────────────────────────────────────────────── */
.wizard-toast {
    position: fixed;
    bottom: var(--pw-space-6);
    right: var(--pw-space-6);
    z-index: 9999;
    padding: var(--pw-space-4) var(--pw-space-5);
    border-radius: var(--pw-radius);
    font-size: var(--pw-text-sm);
    font-weight: var(--pw-weight-medium);
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}

.wizard-toast--success {
    background: rgba(0, 255, 135, 0.15);
    border: 1px solid rgba(0, 255, 135, 0.4);
    color: var(--pw-accent-2);
}

.wizard-toast--error {
    background: rgba(255, 71, 87, 0.15);
    border: 1px solid rgba(255, 71, 87, 0.4);
    color: var(--pw-danger, #ff4757);
}

/* ── Animations ──────────────────────────────────────────────────── */
@keyframes pw-spin { to { transform: rotate(360deg); } }
[x-cloak] { display: none !important; }

/* ── Responsive ──────────────────────────────────────────────────── */
@media (max-width: 640px) {
    .wizard-steps { gap: var(--pw-space-4); }
    .wizard-step__label { display: none; }
    .wizard-panel { padding: var(--pw-space-5); }
    .checkbox-grid, .radio-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<script>
function onboardingWizard() {
    return {
        step: 1,
        maxSteps: 4,
        saving: false,
        toast: { show: false, type: 'success', message: '' },

        contentTypes: ['Reels', 'Posts', 'Stories', 'Videos largos', 'Branding'],
        frequencies: ['Diario', '3x/semana', 'Semanal', 'Quincenal'],
        tones: ['Profesional', 'Casual', 'Energético', 'Informativo'],

        formData: {
            brand_name: '',
            brand_colors: [],
            logo_url: '',
            style_description: '',
            social: {
                instagram: '',
                tiktok: '',
                youtube: '',
                website: '',
            },
            prefs: {
                content_types: [],
                frequency: '',
                tone: '',
            },
            goals: '',
            expected_metrics: '',
        },

        init() {
            // Pre-populate from server-rendered data
            this.formData.brand_name = <?= json_encode($brandName) ?>;
            this.formData.brand_colors = <?= json_encode($brandColors) ?>;
            this.formData.logo_url = <?= json_encode($logoUrl) ?>;
            this.formData.style_description = <?= json_encode($prefs['style_description'] ?? '') ?>;
            this.formData.social = Object.assign(
                { instagram: '', tiktok: '', youtube: '', website: '' },
                <?= json_encode($social) ?>
            );
            this.formData.prefs = Object.assign(
                { content_types: [], frequency: '', tone: '' },
                <?= json_encode($prefs) ?>
            );
            if (!Array.isArray(this.formData.prefs.content_types)) {
                this.formData.prefs.content_types = [];
            }
            this.formData.goals = <?= json_encode($goals) ?>;
            this.formData.expected_metrics = <?= json_encode($prefs['expected_metrics'] ?? '') ?>;
        },

        toggleContentType(type) {
            const idx = this.formData.prefs.content_types.indexOf(type);
            if (idx >= 0) {
                this.formData.prefs.content_types.splice(idx, 1);
            } else {
                this.formData.prefs.content_types.push(type);
            }
        },

        async saveStep() {
            const payload = {};

            if (this.step >= 1) {
                payload.brand_name = this.formData.brand_name;
                payload.brand_colors = this.formData.brand_colors;
                payload.logo_url = this.formData.logo_url;
            }

            if (this.step >= 2) {
                payload.social_accounts = this.formData.social;
            }

            if (this.step >= 3) {
                payload.content_prefs = {
                    ...this.formData.prefs,
                    style_description: this.formData.style_description,
                    expected_metrics: this.formData.expected_metrics,
                };
            }

            if (this.step >= 4) {
                payload.goals = this.formData.goals;
            }

            try {
                const res = await fetch('/api/v1/clients/me/profile', {
                    method: 'PUT',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });

                if (res.status === 401) { window.location.href = '/login'; return false; }

                const body = await res.json();
                return body.success === true;
            } catch (e) {
                return false;
            }
        },

        async nextStep() {
            this.saving = true;
            const ok = await this.saveStep();
            this.saving = false;

            if (ok) {
                this.step = Math.min(this.step + 1, this.maxSteps);
                // Scroll wizard into view
                this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                this.showToast('error', 'Error al guardar. Intenta de nuevo.');
            }
        },

        prevStep() {
            this.step = Math.max(this.step - 1, 1);
            this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        async finish() {
            this.saving = true;

            // Save final step data
            const saved = await this.saveStep();
            if (!saved) {
                this.saving = false;
                this.showToast('error', 'Error al guardar. Intenta de nuevo.');
                return;
            }

            // Mark onboarding complete
            try {
                const res = await fetch('/api/v1/clients/me/onboarding-complete', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                });

                if (res.status === 401) { window.location.href = '/login'; return; }

                const body = await res.json();
                if (body.success) {
                    this.showToast('success', 'Onboarding completado. Redirigiendo...');
                    setTimeout(() => { window.location.href = '/portal'; }, 1500);
                } else {
                    this.showToast('error', body.error?.message ?? 'Error al completar');
                }
            } catch (e) {
                this.showToast('error', 'Error de conexión');
            } finally {
                this.saving = false;
            }
        },

        showToast(type, message) {
            this.toast = { show: true, type, message };
            setTimeout(() => { this.toast.show = false; }, 4000);
        },
    };
}
</script>
