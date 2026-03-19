// ProWay Lab — Main Entry Point (client portal + public pages)
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

// ── HTMX global config ────────────────────────────────────────────────────────
document.addEventListener('htmx:configRequest', (evt) => {
    evt.detail.headers['X-Requested-With'] = 'XMLHttpRequest'
})

document.addEventListener('htmx:responseError', (evt) => {
    if (evt.detail.xhr.status === 401) { window.location.href = '/login'; return }
    const tc = document.getElementById('toast-container')
    if (tc) {
        tc.innerHTML = '<div class="toast toast--error">Error de conexión. Intenta de nuevo.</div>'
        setTimeout(() => { tc.innerHTML = '' }, 5000)
    }
})
