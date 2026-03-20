<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');
?>

<!-- FullCalendar CDN -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

<style>
/* ── ProWay Dark Theme for FullCalendar ────────────────────────────────── */
#pw-calendar {
    --fc-border-color: var(--pw-border, #252528);
    --fc-page-bg-color: transparent;
    --fc-neutral-bg-color: var(--pw-panel, #191920);
    --fc-list-event-hover-bg-color: rgba(0, 217, 255, 0.08);
    --fc-today-bg-color: rgba(0, 217, 255, 0.06);
    --fc-event-border-color: transparent;
    --fc-button-bg-color: var(--pw-panel, #191920);
    --fc-button-border-color: var(--pw-border, #252528);
    --fc-button-text-color: var(--pw-text, #FFFFFF);
    --fc-button-hover-bg-color: rgba(0, 217, 255, 0.12);
    --fc-button-hover-border-color: var(--pw-accent, #00D9FF);
    --fc-button-active-bg-color: rgba(0, 217, 255, 0.18);
    --fc-button-active-border-color: var(--pw-accent, #00D9FF);
    min-height: 500px;
}

/* Toolbar titles */
#pw-calendar .fc-toolbar-title {
    color: var(--pw-text, #FFFFFF);
    font-size: 1.15rem;
    font-weight: 600;
}

/* Button styles */
#pw-calendar .fc-button {
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 500;
    padding: 0.4em 0.85em;
    text-transform: none;
    transition: all 0.15s ease;
    box-shadow: none !important;
}

#pw-calendar .fc-button-active {
    background: var(--pw-accent, #00D9FF) !important;
    border-color: var(--pw-accent, #00D9FF) !important;
    color: var(--pw-black, #0C0C0F) !important;
    font-weight: 600;
}

/* Day headers */
#pw-calendar .fc-col-header-cell {
    background: var(--pw-panel, #191920);
    border-color: var(--pw-border, #252528);
}

#pw-calendar .fc-col-header-cell-cushion {
    color: var(--pw-text-muted, #A1A1AA);
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.55em 0;
    text-decoration: none;
}

/* Day cells */
#pw-calendar .fc-daygrid-day-number {
    color: var(--pw-text, #FFFFFF);
    font-size: 0.82rem;
    padding: 6px 8px;
    text-decoration: none;
}

#pw-calendar .fc-day-other .fc-daygrid-day-number {
    color: var(--pw-text-muted, #A1A1AA);
    opacity: 0.4;
}

/* Today highlight */
#pw-calendar .fc-day-today {
    background: rgba(0, 217, 255, 0.06) !important;
}

#pw-calendar .fc-day-today .fc-daygrid-day-number {
    background: var(--pw-accent, #00D9FF);
    color: var(--pw-black, #0C0C0F);
    border-radius: 50%;
    width: 26px;
    height: 26px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

/* Events */
#pw-calendar .fc-event {
    border-radius: 5px;
    border: none;
    padding: 2px 6px;
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
    transition: opacity 0.15s ease, transform 0.15s ease;
}

#pw-calendar .fc-event:hover {
    opacity: 0.85;
    transform: translateY(-1px);
}

#pw-calendar .fc-event-title {
    font-weight: 500;
}

/* List view */
#pw-calendar .fc-list {
    border-color: var(--pw-border, #252528);
}

#pw-calendar .fc-list-sticky .fc-list-day > * {
    background: var(--pw-panel, #191920);
}

#pw-calendar .fc-list-day-cushion {
    background: var(--pw-panel, #191920) !important;
}

#pw-calendar .fc-list-day-text,
#pw-calendar .fc-list-day-side-text {
    color: var(--pw-text, #FFFFFF);
    text-decoration: none;
}

#pw-calendar .fc-list-event td {
    border-color: var(--pw-border, #252528);
}

#pw-calendar .fc-list-event-title a {
    color: var(--pw-text, #FFFFFF);
    text-decoration: none;
}

#pw-calendar .fc-list-event-time {
    color: var(--pw-text-muted, #A1A1AA);
}

/* Scrollbar */
#pw-calendar .fc-scroller::-webkit-scrollbar {
    width: 6px;
}
#pw-calendar .fc-scroller::-webkit-scrollbar-track {
    background: var(--pw-dark, #111114);
}
#pw-calendar .fc-scroller::-webkit-scrollbar-thumb {
    background: var(--pw-border, #252528);
    border-radius: 3px;
}

/* Legend bar */
.pw-calendar-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    padding: 0.75rem 0;
    margin-bottom: 0.5rem;
}
.pw-calendar-legend__item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: var(--pw-text-muted, #A1A1AA);
}
.pw-calendar-legend__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 768px) {
    #pw-calendar .fc-toolbar {
        flex-direction: column;
        gap: 0.5rem;
    }
    #pw-calendar .fc-toolbar-title {
        font-size: 1rem;
    }
    #pw-calendar .fc-button {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    .pw-calendar-legend {
        gap: 0.6rem;
    }
}
</style>

<!-- Legend -->
<div class="pw-calendar-legend">
    <span class="pw-calendar-legend__item">
        <span class="pw-calendar-legend__dot" style="background:#00FF87;"></span>
        Pagado / Entregado
    </span>
    <span class="pw-calendar-legend__item">
        <span class="pw-calendar-legend__dot" style="background:#00D9FF;"></span>
        En Produccion / Revision
    </span>
    <span class="pw-calendar-legend__item">
        <span class="pw-calendar-legend__dot" style="background:#FACC15;"></span>
        Confirmado / Cotizacion
    </span>
    <span class="pw-calendar-legend__item">
        <span class="pw-calendar-legend__dot" style="background:#E31E24;"></span>
        Vencido
    </span>
</div>

<!-- Calendar container -->
<div id="pw-calendar"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('pw-calendar');
    if (!calendarEl) return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'es',
        initialView: 'dayGridMonth',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listWeek'
        },
        buttonText: {
            today:    'Hoy',
            month:    'Mes',
            week:     'Semana',
            list:     'Lista'
        },
        height: 'auto',
        navLinks: true,
        editable: false,
        dayMaxEvents: 3,
        moreLinkText: function(n) { return '+' + n + ' mas'; },
        events: function(info, successCallback, failureCallback) {
            fetch('/api/v1/calendar/events', {
                method: 'GET',
                credentials: 'include',
                headers: { 'Accept': 'application/json' }
            })
            .then(function(res) {
                if (res.status === 401) {
                    window.location.href = '/login';
                    return;
                }
                return res.json();
            })
            .then(function(json) {
                if (json && json.success && json.data && json.data.events) {
                    successCallback(json.data.events);
                } else {
                    successCallback([]);
                }
            })
            .catch(function(err) {
                console.error('Calendar fetch error:', err);
                failureCallback(err);
            });
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                window.location.href = info.event.url;
            }
        },
        eventDidMount: function(info) {
            // Add tooltip with status and client name
            var props = info.event.extendedProps || {};
            var statusLabels = {
                'cotizacion': 'Cotizacion',
                'confirmado': 'Confirmado',
                'en_produccion': 'En Produccion',
                'revision': 'Revision',
                'entregado': 'Entregado',
                'facturado': 'Facturado',
                'pagado': 'Pagado'
            };
            var parts = [];
            if (props.status) parts.push(statusLabels[props.status] || props.status);
            if (props.client_name) parts.push(props.client_name);
            if (parts.length) {
                info.el.title = parts.join(' — ');
            }
        }
    });

    calendar.render();
});
</script>
