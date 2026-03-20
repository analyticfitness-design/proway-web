/**
 * ProWay Lab — Error Reporter
 * Captures uncaught JS errors, unhandled promise rejections, and fetch failures.
 * Sends error reports to POST /api/v1/errors.
 */
(function () {
    'use strict'

    const ENDPOINT = '/api/v1/errors'
    const MAX_QUEUE = 10
    let queue = 0

    function send(payload) {
        if (queue >= MAX_QUEUE) return
        queue++
        fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
            .catch(function () { /* silent — avoid recursive error loops */ })
            .finally(function () { queue-- })
    }

    // ── Global JS errors ────────────────────────────────────────────────────────
    window.addEventListener('error', function (evt) {
        send({
            level: 'error',
            message: evt.message || 'Unknown error',
            stack: evt.error ? evt.error.stack : null,
            url: evt.filename || window.location.href,
            context: { line: evt.lineno, col: evt.colno },
        })
    })

    // ── Unhandled promise rejections ────────────────────────────────────────────
    window.addEventListener('unhandledrejection', function (evt) {
        var reason = evt.reason
        var message = 'Unhandled Promise Rejection'
        var stack = null

        if (reason instanceof Error) {
            message = reason.message || message
            stack = reason.stack || null
        } else if (typeof reason === 'string') {
            message = reason
        }

        send({
            level: 'error',
            message: message,
            stack: stack,
            url: window.location.href,
            context: { type: 'unhandledrejection' },
        })
    })

    // ── Fetch error interception ────────────────────────────────────────────────
    var originalFetch = window.fetch
    window.fetch = function () {
        return originalFetch.apply(this, arguments).then(function (response) {
            if (!response.ok && response.status >= 500) {
                send({
                    level: 'warning',
                    message: 'Fetch ' + response.status + ': ' + response.url,
                    url: window.location.href,
                    context: { status: response.status, fetchUrl: response.url },
                })
            }
            return response
        })
    }
})()
