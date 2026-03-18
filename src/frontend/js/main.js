// ProWay Lab — Main Entry Point
// Handles: public pages, client portal
import '../css/main.css'
import 'htmx.org'
import Alpine from 'alpinejs'

// Initialize Alpine.js
window.Alpine = Alpine
Alpine.start()

// HTMX global config
document.addEventListener('htmx:configRequest', (evt) => {
    // Add CSRF protection header for all HTMX requests
    evt.detail.headers['X-Requested-With'] = 'XMLHttpRequest'
})

// Handle HTMX errors globally
document.addEventListener('htmx:responseError', (evt) => {
    console.error('[ProWay] HTMX error:', evt.detail.xhr.status, evt.detail.xhr.statusText)
    const toastContainer = document.getElementById('toast-container')
    if (toastContainer) {
        toastContainer.innerHTML = '<div class="toast toast-error">Error de conexión. Intenta de nuevo.</div>'
        setTimeout(() => { toastContainer.innerHTML = '' }, 5000)
    }
})

console.log('[ProWay] Frontend initialized')
