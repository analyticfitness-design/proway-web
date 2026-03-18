// ProWay Lab — Admin Entry Point
import '../css/main.css'
import 'htmx.org'
import Alpine from 'alpinejs'

window.Alpine = Alpine
Alpine.start()

document.addEventListener('htmx:configRequest', (evt) => {
    evt.detail.headers['X-Requested-With'] = 'XMLHttpRequest'
})

// Admin-specific: refresh stats every 60 seconds
document.addEventListener('DOMContentLoaded', () => {
    const statsEl = document.getElementById('admin-stats')
    if (statsEl) {
        setInterval(() => {
            htmx.trigger(statsEl, 'refresh')
        }, 60000)
    }
})

console.log('[ProWay Admin] Frontend initialized')
