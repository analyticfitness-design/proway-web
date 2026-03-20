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
