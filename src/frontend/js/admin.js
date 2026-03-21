// ProWay Lab — Admin Entry Point
import './error-reporter.js'
import '../css/main.css'
import 'htmx.org'
import Alpine from 'alpinejs'

window.Alpine = Alpine

// ── Alpine: statusModal component (PATCH project/invoice status inline) ───────
Alpine.data('statusModal', (type) => ({
    open: false,
    itemId: null,
    currentStatus: '',
    saving: false,
    statuses: type === 'project'
        ? ['pendiente', 'en_progreso', 'revision', 'completado']
        : ['pendiente', 'enviada', 'pagada', 'vencida'],

    openFor(id, current) {
        this.itemId = id
        this.currentStatus = current
        this.open = true
    },

    async update(newStatus) {
        this.saving = true
        const endpoint = type === 'project'
            ? '/api/v1/projects/' + this.itemId + '/status'
            : '/api/v1/invoices/' + this.itemId + '/status'
        try {
            const res = await fetch(endpoint, {
                method: 'PATCH',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: newStatus }),
            })
            const body = await res.json()
            if (body.success) {
                this.open = false
                // Re-trigger the relevant HTMX partial
                const partial = document.getElementById(type + '-list')
                if (partial) htmx.trigger(partial, 'refresh')
            }
        } catch (_) {
            console.error('[Admin] status update failed')
        } finally {
            this.saving = false
        }
    },
}))

// ── Alpine: kanbanBoard component (drag & drop project management) ───────────
Alpine.data('kanbanBoard', () => ({
    dragging: null,

    dragStart(e, projectId) {
        this.dragging = { id: projectId };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(projectId));
        // Add visual feedback
        requestAnimationFrame(() => {
            e.target.classList.add('dragging');
        });
    },

    dragEnd(e) {
        e.target.classList.remove('dragging');
        // Remove drag-over from all columns
        document.querySelectorAll('.kanban__column').forEach(col => {
            col.classList.remove('drag-over');
        });
    },

    dragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        // Highlight the column
        const column = e.target.closest('.kanban__column');
        if (column) {
            document.querySelectorAll('.kanban__column').forEach(c => c.classList.remove('drag-over'));
            column.classList.add('drag-over');
        }
    },

    async drop(e, targetStatus) {
        e.preventDefault();
        if (!this.dragging) return;

        // Remove drag-over from all columns
        document.querySelectorAll('.kanban__column').forEach(col => {
            col.classList.remove('drag-over');
        });

        // Calculate target order: count existing cards in this column
        const column = e.target.closest('.kanban__column');
        const cards = column ? column.querySelectorAll('.kanban__card') : [];
        const targetOrder = cards.length;

        try {
            const res = await fetch('/api/v1/admin/kanban/' + this.dragging.id, {
                method: 'PATCH',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: targetStatus, order: targetOrder }),
            });

            if (res.status === 401) {
                window.location.href = '/login';
                return;
            }

            if (!res.ok) {
                console.error('[Kanban] move failed');
            }
        } catch (err) {
            console.error('[Kanban] network error', err);
        }

        this.dragging = null;
        // Refresh the board
        htmx.trigger('#kanban-container', 'load');
    },
}));

Alpine.start()

// ── HTMX global config ────────────────────────────────────────────────────────
document.addEventListener('htmx:configRequest', (evt) => {
    evt.detail.headers['X-Requested-With'] = 'XMLHttpRequest'
})

document.addEventListener('htmx:responseError', (evt) => {
    if (evt.detail.xhr.status === 401) { window.location.href = '/login'; return }
    if (evt.detail.xhr.status === 403) {
        document.body.innerHTML = '<div style="padding:2rem;text-align:center"><h2>Acceso denegado</h2></div>'
        return
    }
})

// Auto-refresh admin stats every 60 s
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('admin-stats')
    if (el) setInterval(() => htmx.trigger(el, 'refresh'), 60000)
})
