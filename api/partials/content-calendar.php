<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

use ProWay\Domain\ContentCalendar\ContentCalendarService;
use ProWay\Domain\ContentCalendar\MySQLContentSlotRepository;

// ── Determine which view ────────────────────────────────────────────────────
$isAdmin   = $currentUser->type === 'admin';
$clientId  = $isAdmin ? (isset($_GET['client_id']) && $_GET['client_id'] !== '' ? (int) $_GET['client_id'] : null) : $currentUser->id;

// ── Month navigation ────────────────────────────────────────────────────────
$monthParam = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}

$year  = (int) substr($monthParam, 0, 4);
$month = (int) substr($monthParam, 5, 2);

$firstDay     = "{$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . "-01";
$daysInMonth  = (int) date('t', strtotime($firstDay));
$lastDay      = "{$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . "-{$daysInMonth}";
$firstWeekday = (int) date('N', strtotime($firstDay)); // 1=Mon … 7=Sun

$prevMonth = date('Y-m', strtotime("{$firstDay} -1 month"));
$nextMonth = date('Y-m', strtotime("{$firstDay} +1 month"));
$monthLabel = ucfirst(strftime('%B %Y', strtotime($firstDay)));
// strftime may not work on all systems, fallback:
$monthNames = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$monthLabel = ($monthNames[$month] ?? '') . ' ' . $year;

// ── Fetch slots ─────────────────────────────────────────────────────────────
try {
    $calendarService = new ContentCalendarService(new MySQLContentSlotRepository($pdo));
    if ($isAdmin) {
        $slots = $calendarService->getAdminCalendar($firstDay, $lastDay, $clientId);
    } else {
        $slots = $calendarService->getClientCalendar($currentUser->id, 90);
        // Filter to current month
        $slots = array_filter($slots, fn($s) => $s['scheduled_date'] >= $firstDay && $s['scheduled_date'] <= $lastDay);
        $slots = array_values($slots);
    }
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar el calendario. Inténtalo de nuevo.</div>';
    exit;
}

// Group slots by date
$slotsByDate = [];
foreach ($slots as $slot) {
    $d = $slot['scheduled_date'];
    $slotsByDate[$d][] = $slot;
}

// ── Fetch clients for admin filter ──────────────────────────────────────────
$clients = [];
if ($isAdmin) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM clients WHERE status = 'activo' ORDER BY name ASC");
        $clients = $stmt->fetchAll();
    } catch (Throwable) {}
}

// ── Colors ──────────────────────────────────────────────────────────────────
$typeColors = [
    'reel'      => '#00D9FF',
    'story'     => '#A855F7',
    'post'      => '#22C55E',
    'video'     => '#FBBF24',
    'carousel'  => '#F97316',
];

$statusLabels = [
    'planned'       => 'Planeado',
    'in_production' => 'En Producción',
    'ready'         => 'Listo',
    'published'     => 'Publicado',
    'cancelled'     => 'Cancelado',
];

$platformIcons = [
    'instagram' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5"/></svg>',
    'tiktok'    => '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 00-.79-.05A6.34 6.34 0 003.15 15.2a6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.34-6.34V9.18a8.16 8.16 0 004.76 1.52v-3.4a4.85 4.85 0 01-1-.61z"/></svg>',
    'youtube'   => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22.54 6.42a2.78 2.78 0 00-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 00-1.94 2A29 29 0 001 12a29 29 0 00.46 5.58 2.78 2.78 0 001.94 2C5.12 20 12 20 12 20s6.88 0 8.6-.46a2.78 2.78 0 001.94-2A29 29 0 0023 12a29 29 0 00-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"/></svg>',
    'facebook'  => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>',
];

// Build HTMX URL for navigation
$baseUrl = '/api/partials/content-calendar.php?month=';
$clientParam = $clientId !== null ? "&client_id={$clientId}" : '';
$today = date('Y-m-d');
?>

<div class="ccal" x-data="contentCalendar()" id="content-calendar-root">

    <!-- Header: month nav + legend -->
    <div class="ccal__header">
        <div class="ccal__nav">
            <button class="btn btn--ghost btn--sm"
                    hx-get="<?= $baseUrl . $prevMonth . $clientParam ?>"
                    hx-target="#calendar-container"
                    hx-swap="innerHTML"
                    hx-indicator="#cal-spinner"
                    title="Mes anterior">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <span class="ccal__month-label"><?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <button class="btn btn--ghost btn--sm"
                    hx-get="<?= $baseUrl . $nextMonth . $clientParam ?>"
                    hx-target="#calendar-container"
                    hx-swap="innerHTML"
                    hx-indicator="#cal-spinner"
                    title="Mes siguiente">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <div class="ccal__legend">
            <?php foreach ($typeColors as $type => $color): ?>
            <span class="ccal__legend-item">
                <span class="ccal__legend-dot" style="background:<?= $color ?>;"></span>
                <?= ucfirst($type) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Day-of-week headers -->
    <div class="ccal__grid ccal__grid--header">
        <div class="ccal__dow">Lun</div>
        <div class="ccal__dow">Mar</div>
        <div class="ccal__dow">Mié</div>
        <div class="ccal__dow">Jue</div>
        <div class="ccal__dow">Vie</div>
        <div class="ccal__dow ccal__dow--weekend">Sáb</div>
        <div class="ccal__dow ccal__dow--weekend">Dom</div>
    </div>

    <!-- Calendar grid -->
    <div class="ccal__grid ccal__grid--days">
        <?php
        // Empty cells before first day
        for ($i = 1; $i < $firstWeekday; $i++):
        ?>
        <div class="ccal__day ccal__day--empty"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $daysInMonth; $d++):
            $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $daySlots = $slotsByDate[$dateStr] ?? [];
            $isToday  = $dateStr === $today;
        ?>
        <div class="ccal__day <?= $isToday ? 'ccal__day--today' : '' ?>"
             <?php if ($isAdmin): ?>
             @click="openCreate('<?= $dateStr ?>')"
             style="cursor:pointer;"
             title="Click para agregar contenido"
             <?php endif; ?>>

            <div class="ccal__day-num <?= $isToday ? 'ccal__day-num--today' : '' ?>"><?= $d ?></div>

            <div class="ccal__day-slots">
                <?php foreach ($daySlots as $slot):
                    $color = $typeColors[$slot['content_type']] ?? '#6b7280';
                    $slotTitle = htmlspecialchars($slot['title'] ?? ucfirst($slot['content_type']), ENT_QUOTES, 'UTF-8');
                    $platform  = $slot['platform'] ?? '';
                    $platformIcon = $platformIcons[$platform] ?? '';
                    $slotJson  = htmlspecialchars(json_encode($slot, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="ccal__chip"
                     style="--chip-color: <?= $color ?>;"
                     @click.stop="openEdit(<?= $slotJson ?>)"
                     title="<?= htmlspecialchars($statusLabels[$slot['status']] ?? $slot['status'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($platformIcon): ?>
                    <span class="ccal__chip-platform"><?= $platformIcon ?></span>
                    <?php endif; ?>
                    <span class="ccal__chip-label"><?= $slotTitle ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endfor; ?>

        <?php
        // Empty cells after last day to fill the row
        $lastWeekday = (int) date('N', strtotime($lastDay));
        for ($i = $lastWeekday + 1; $i <= 7; $i++):
        ?>
        <div class="ccal__day ccal__day--empty"></div>
        <?php endfor; ?>
    </div>

    <?php if ($isAdmin): ?>
    <!-- ── Create Modal ─────────────────────────────────────────────────────── -->
    <div class="ccal__modal-overlay" x-show="showCreate" x-cloak @click.self="showCreate = false"
         x-transition:enter="fade-in" x-transition:leave="fade-out">
        <div class="ccal__modal">
            <div class="ccal__modal-header">
                <h3>Nuevo Contenido</h3>
                <button class="btn btn--ghost btn--sm" @click="showCreate = false">&times;</button>
            </div>
            <form @submit.prevent="submitCreate()">
                <div class="ccal__modal-body">
                    <div class="ccal__field">
                        <label>Fecha</label>
                        <input type="date" x-model="form.scheduled_date" required class="ccal__input">
                    </div>
                    <div class="ccal__field">
                        <label>Cliente</label>
                        <select x-model="form.client_id" required class="ccal__input">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ccal__field">
                        <label>Tipo de Contenido</label>
                        <select x-model="form.content_type" required class="ccal__input">
                            <option value="">Seleccionar...</option>
                            <option value="reel">Reel</option>
                            <option value="story">Story</option>
                            <option value="post">Post</option>
                            <option value="video">Video</option>
                            <option value="carousel">Carousel</option>
                        </select>
                    </div>
                    <div class="ccal__field">
                        <label>Título</label>
                        <input type="text" x-model="form.title" class="ccal__input" placeholder="Título del contenido">
                    </div>
                    <div class="ccal__field">
                        <label>Plataforma</label>
                        <select x-model="form.platform" class="ccal__input">
                            <option value="">Sin plataforma</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="youtube">YouTube</option>
                            <option value="facebook">Facebook</option>
                        </select>
                    </div>
                    <div class="ccal__field">
                        <label>Descripción</label>
                        <textarea x-model="form.description" class="ccal__input ccal__textarea" rows="3" placeholder="Notas o descripción..."></textarea>
                    </div>
                </div>
                <div class="ccal__modal-footer">
                    <button type="button" class="btn btn--ghost btn--sm" @click="showCreate = false">Cancelar</button>
                    <button type="submit" class="btn btn--primary btn--sm" :disabled="saving">
                        <span x-show="!saving">Crear Slot</span>
                        <span x-show="saving">Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Edit Modal ────────────────────────────────────────────────────────── -->
    <div class="ccal__modal-overlay" x-show="showEdit" x-cloak @click.self="showEdit = false"
         x-transition:enter="fade-in" x-transition:leave="fade-out">
        <div class="ccal__modal">
            <div class="ccal__modal-header">
                <h3>Editar Contenido</h3>
                <button class="btn btn--ghost btn--sm" @click="showEdit = false">&times;</button>
            </div>
            <form @submit.prevent="submitEdit()">
                <div class="ccal__modal-body">
                    <div class="ccal__field">
                        <label>Fecha</label>
                        <input type="date" x-model="editForm.scheduled_date" required class="ccal__input">
                    </div>
                    <div class="ccal__field">
                        <label>Cliente</label>
                        <select x-model="editForm.client_id" required class="ccal__input">
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ccal__field">
                        <label>Tipo de Contenido</label>
                        <select x-model="editForm.content_type" required class="ccal__input">
                            <option value="reel">Reel</option>
                            <option value="story">Story</option>
                            <option value="post">Post</option>
                            <option value="video">Video</option>
                            <option value="carousel">Carousel</option>
                        </select>
                    </div>
                    <div class="ccal__field">
                        <label>Título</label>
                        <input type="text" x-model="editForm.title" class="ccal__input">
                    </div>
                    <div class="ccal__field">
                        <label>Estado</label>
                        <select x-model="editForm.status" class="ccal__input">
                            <option value="planned">Planeado</option>
                            <option value="in_production">En Producción</option>
                            <option value="ready">Listo</option>
                            <option value="published">Publicado</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                    <div class="ccal__field">
                        <label>Plataforma</label>
                        <select x-model="editForm.platform" class="ccal__input">
                            <option value="">Sin plataforma</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="youtube">YouTube</option>
                            <option value="facebook">Facebook</option>
                        </select>
                    </div>
                    <div class="ccal__field">
                        <label>Descripción</label>
                        <textarea x-model="editForm.description" class="ccal__input ccal__textarea" rows="3"></textarea>
                    </div>
                </div>
                <div class="ccal__modal-footer">
                    <button type="button" class="btn btn--ghost btn--sm ccal__btn-delete" @click="confirmDelete()">Eliminar</button>
                    <div style="display:flex;gap:var(--pw-space-2);">
                        <button type="button" class="btn btn--ghost btn--sm" @click="showEdit = false">Cancelar</button>
                        <button type="submit" class="btn btn--primary btn--sm" :disabled="saving">
                            <span x-show="!saving">Guardar</span>
                            <span x-show="saving">Guardando...</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
/* ── Content Calendar ────────────────────────────────────────────────────── */
.ccal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--pw-space-3);
    margin-bottom: var(--pw-space-3);
}

.ccal__nav {
    display: flex;
    align-items: center;
    gap: var(--pw-space-2);
}

.ccal__month-label {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--pw-text);
    min-width: 160px;
    text-align: center;
}

.ccal__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.ccal__legend-item {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.75rem;
    color: var(--pw-text-muted);
}

.ccal__legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Grid ────────────────────────────────────────────────────────────────── */
.ccal__grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--pw-border);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-lg);
    overflow: hidden;
}

.ccal__grid--header {
    border-radius: var(--pw-radius-lg) var(--pw-radius-lg) 0 0;
    border-bottom: none;
}

.ccal__grid--days {
    border-radius: 0 0 var(--pw-radius-lg) var(--pw-radius-lg);
    border-top: none;
}

.ccal__dow {
    background: var(--pw-panel);
    padding: var(--pw-space-2);
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--pw-text-muted);
}

.ccal__dow--weekend {
    color: var(--pw-text-muted);
    opacity: 0.6;
}

/* ── Day cell ────────────────────────────────────────────────────────────── */
.ccal__day {
    background: var(--pw-bg);
    min-height: 90px;
    padding: var(--pw-space-1);
    display: flex;
    flex-direction: column;
    transition: background 0.15s;
}

.ccal__day:not(.ccal__day--empty):hover {
    background: rgba(0, 217, 255, 0.04);
}

.ccal__day--empty {
    background: var(--pw-panel);
    opacity: 0.4;
}

.ccal__day--today {
    background: rgba(0, 217, 255, 0.06);
}

.ccal__day-num {
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--pw-text-muted);
    padding: 2px 6px;
    align-self: flex-end;
}

.ccal__day-num--today {
    background: var(--pw-accent, #00D9FF);
    color: var(--pw-black, #0C0C0F);
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.ccal__day-slots {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 2px;
    overflow-y: auto;
    max-height: 80px;
}

/* ── Chip (slot indicator) ───────────────────────────────────────────────── */
.ccal__chip {
    display: flex;
    align-items: center;
    gap: 3px;
    background: color-mix(in srgb, var(--chip-color) 15%, transparent);
    border-left: 3px solid var(--chip-color);
    border-radius: 3px;
    padding: 2px 5px;
    font-size: 0.68rem;
    color: var(--pw-text);
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.ccal__chip:hover {
    background: color-mix(in srgb, var(--chip-color) 25%, transparent);
    transform: translateX(1px);
}

.ccal__chip-platform {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    opacity: 0.7;
}

.ccal__chip-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* ── Modal ───────────────────────────────────────────────────────────────── */
.ccal__modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: var(--pw-space-4);
}

.ccal__modal {
    background: var(--pw-panel);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-lg);
    width: 100%;
    max-width: 480px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.ccal__modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--pw-space-3) var(--pw-space-4);
    border-bottom: 1px solid var(--pw-border);
}

.ccal__modal-header h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--pw-text);
    margin: 0;
}

.ccal__modal-body {
    padding: var(--pw-space-4);
    display: flex;
    flex-direction: column;
    gap: var(--pw-space-3);
}

.ccal__modal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--pw-space-3) var(--pw-space-4);
    border-top: 1px solid var(--pw-border);
}

.ccal__field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ccal__field label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--pw-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.ccal__input {
    background: var(--pw-bg);
    border: 1px solid var(--pw-border);
    border-radius: var(--pw-radius-md);
    padding: var(--pw-space-2) var(--pw-space-3);
    font-size: 0.85rem;
    color: var(--pw-text);
    transition: border-color 0.15s;
    width: 100%;
    font-family: inherit;
}

.ccal__input:focus {
    outline: none;
    border-color: var(--pw-accent, #00D9FF);
    box-shadow: 0 0 0 2px rgba(0, 217, 255, 0.15);
}

.ccal__textarea {
    resize: vertical;
    min-height: 60px;
}

.ccal__btn-delete {
    color: #ef4444 !important;
}

.ccal__btn-delete:hover {
    background: rgba(239, 68, 68, 0.1) !important;
}

/* ── Transitions ─────────────────────────────────────────────────────────── */
.fade-in  { animation: ccal-fade-in 0.15s ease; }
.fade-out { animation: ccal-fade-out 0.15s ease; }

@keyframes ccal-fade-in  { from { opacity: 0; } to { opacity: 1; } }
@keyframes ccal-fade-out { from { opacity: 1; } to { opacity: 0; } }

[x-cloak] { display: none !important; }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .ccal__header { flex-direction: column; align-items: flex-start; }
    .ccal__day { min-height: 60px; }
    .ccal__day-slots { max-height: 50px; }
    .ccal__chip { font-size: 0.6rem; padding: 1px 3px; }
    .ccal__dow { font-size: 0.65rem; padding: var(--pw-space-1); }
    .ccal__modal { max-width: 100%; margin: var(--pw-space-2); }
}

@media (max-width: 480px) {
    .ccal__day { min-height: 45px; padding: 1px; }
    .ccal__day-num { font-size: 0.7rem; padding: 1px 3px; }
    .ccal__chip-label { display: none; }
    .ccal__chip { justify-content: center; padding: 2px; }
}
</style>

<script>
function contentCalendar() {
    return {
        showCreate: false,
        showEdit: false,
        saving: false,
        form: {
            scheduled_date: '',
            client_id: '',
            content_type: '',
            title: '',
            description: '',
            platform: '',
        },
        editForm: {
            id: null,
            scheduled_date: '',
            client_id: '',
            content_type: '',
            title: '',
            description: '',
            status: 'planned',
            platform: '',
        },

        openCreate(dateStr) {
            this.form = {
                scheduled_date: dateStr,
                client_id: '<?= $clientId ?? '' ?>',
                content_type: '',
                title: '',
                description: '',
                platform: '',
            };
            this.showCreate = true;
        },

        openEdit(slot) {
            this.editForm = {
                id: slot.id,
                scheduled_date: slot.scheduled_date,
                client_id: String(slot.client_id),
                content_type: slot.content_type,
                title: slot.title || '',
                description: slot.description || '',
                status: slot.status,
                platform: slot.platform || '',
            };
            this.showEdit = true;
        },

        async submitCreate() {
            if (this.saving) return;
            this.saving = true;
            try {
                const res = await fetch('/api/v1/admin/content-calendar', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                if (res.status === 401) { window.location.href = '/login'; return; }
                const json = await res.json();
                if (json.success) {
                    this.showCreate = false;
                    htmx.ajax('GET', '/api/partials/content-calendar.php?month=<?= $monthParam . $clientParam ?>', '#calendar-container');
                } else {
                    alert(json.error?.message || 'Error al crear el slot.');
                }
            } catch (e) {
                alert('Error de conexión.');
            } finally {
                this.saving = false;
            }
        },

        async submitEdit() {
            if (this.saving) return;
            this.saving = true;
            try {
                const res = await fetch('/api/v1/admin/content-calendar/' + this.editForm.id, {
                    method: 'PATCH',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.editForm),
                });
                if (res.status === 401) { window.location.href = '/login'; return; }
                const json = await res.json();
                if (json.success) {
                    this.showEdit = false;
                    htmx.ajax('GET', '/api/partials/content-calendar.php?month=<?= $monthParam . $clientParam ?>', '#calendar-container');
                } else {
                    alert(json.error?.message || 'Error al actualizar.');
                }
            } catch (e) {
                alert('Error de conexión.');
            } finally {
                this.saving = false;
            }
        },

        async confirmDelete() {
            if (!confirm('¿Estás seguro de eliminar este slot?')) return;
            this.saving = true;
            try {
                const res = await fetch('/api/v1/admin/content-calendar/' + this.editForm.id, {
                    method: 'DELETE',
                    credentials: 'include',
                });
                if (res.status === 401) { window.location.href = '/login'; return; }
                const json = await res.json();
                if (json.success) {
                    this.showEdit = false;
                    htmx.ajax('GET', '/api/partials/content-calendar.php?month=<?= $monthParam . $clientParam ?>', '#calendar-container');
                } else {
                    alert(json.error?.message || 'Error al eliminar.');
                }
            } catch (e) {
                alert('Error de conexión.');
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
