// ProWay Lab — Main Entry Point (client portal + public pages)
import './error-reporter.js'
import '../css/main.css'
import 'htmx.org'
import Alpine from 'alpinejs'

window.Alpine = Alpine

// ── Alpine: profileForm component ─────────────────────────────────────────────
Alpine.data('profileForm', () => ({
    id: null,
    original: {},
    form: { nombre: '', telefono: '', objetivo: '', notas: '' },
    loading: false,
    saving: false,
    toast: { show: false, type: 'success', message: '' },

    get isDirty() {
        return JSON.stringify(this.form) !== JSON.stringify(this.original)
    },

    async init() {
        this.loading = true
        try {
            const res = await fetch('/api/v1/clients/me', { credentials: 'include' })
            if (res.status === 401) { window.location.href = '/login'; return }
            const { data } = await res.json()
            const c = data.client
            this.id = c.id
            this.form = { nombre: c.nombre ?? '', telefono: c.telefono ?? '', objetivo: c.objetivo ?? '', notas: c.notas ?? '' }
            this.original = { ...this.form }
        } catch (_) {
            this.showToast('error', 'Error al cargar perfil')
        } finally {
            this.loading = false
        }
    },

    async save() {
        this.saving = true
        try {
            const res = await fetch('/api/v1/clients/' + this.id, {
                method: 'PUT',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.form),
            })
            const body = await res.json()
            if (body.success) {
                this.original = { ...this.form }
                this.showToast('success', 'Perfil actualizado correctamente')
            } else {
                this.showToast('error', body.error?.message ?? 'Error al guardar')
            }
        } catch (_) {
            this.showToast('error', 'Error de conexión')
        } finally {
            this.saving = false
        }
    },

    discard() { this.form = { ...this.original } },

    showToast(type, message) {
        this.toast = { show: true, type, message }
        setTimeout(() => { this.toast.show = false }, 4000)
    },
}))

Alpine.start()

// ── Onboarding redirect ──────────────────────────────────────────────────────────
// On portal pages (not the onboarding page itself), check if onboarding is pending
// and redirect the client to complete it.
;(async function checkOnboarding() {
    const path = window.location.pathname
    const portalPages = ['/portal', '/proyectos', '/proyecto', '/facturas', '/calendario', '/perfil']
    if (!portalPages.some(p => path === p || path.startsWith(p + '?'))) return

    try {
        const res = await fetch('/api/v1/clients/me/profile', { credentials: 'include' })
        if (!res.ok) return
        const body = await res.json()
        if (body.success && body.data?.profile?.onboarding_done === 0) {
            window.location.href = '/onboarding'
        }
    } catch (_) {
        // Silently fail — don't block the page
    }
})()

// ── Wompi checkout ─────────────────────────────────────────────────────────────
// Global function called via onclick from HTMX-loaded invoice table rows.
// Uses textContent and safe DOM methods — no innerHTML with dynamic content.
window.wompiPay = async function wompiPay(invoiceId, btn) {
    const originalText = btn ? btn.textContent.trim() : ''
    if (btn) {
        btn.disabled = true
        btn.textContent = 'Redirigiendo\u2026'
    }

    try {
        const res = await fetch('/api/v1/payments/checkout', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoice_id: invoiceId }),
        })

        if (res.status === 401) { window.location.href = '/login'; return }

        const body = await res.json()

        if (!body.success) {
            _wompiShowError(body.error?.message ?? 'Error al iniciar el pago')
            if (btn) { btn.disabled = false; btn.textContent = originalText }
            return
        }

        const d = body.data.checkout
        const params = new URLSearchParams({
            'public-key':          d.public_key,
            'currency':            d.currency ?? 'COP',
            'amount-in-cents':     String(d.amount_in_cents),
            'reference':           d.reference,
            'customer-email':      d.customer_email ?? '',
            'signature:integrity': d.signature.integrity,
        })

        window.location.href = 'https://checkout.wompi.co/p/?' + params.toString()
    } catch (_) {
        _wompiShowError('Error de conexi\u00f3n. Intenta de nuevo.')
        if (btn) { btn.disabled = false; btn.textContent = originalText }
    }
}

function _wompiShowError(msg) {
    const tc = document.getElementById('toast-container')
    if (!tc) return
    const toast = document.createElement('div')
    toast.className = 'toast toast--error'
    toast.textContent = msg
    tc.replaceChildren(toast)
    setTimeout(() => { tc.replaceChildren() }, 6000)
}

// ── HTMX global config ────────────────────────────────────────────────────────
document.addEventListener('htmx:configRequest', (evt) => {
    evt.detail.headers['X-Requested-With'] = 'XMLHttpRequest'
})

document.addEventListener('htmx:responseError', (evt) => {
    if (evt.detail.xhr.status === 401) { window.location.href = '/login'; return }
    _wompiShowError('Error de conexi\u00f3n. Intenta de nuevo.')
})
