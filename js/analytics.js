/* ================================================================
   ProWay Lab — Analytics & Conversion Tracking v1.0
   Google Analytics 4 + Meta Pixel
   Include in all public pages: <script src="/js/analytics.js" defer></script>
   ================================================================ */
(function(){
    'use strict';

    // ── CONFIG ──────────────────────────────────────────────────
    // Replace with your real IDs when ready
    var GA_ID    = 'G-XXXXXXXXXX';      // Google Analytics 4 Measurement ID
    var PIXEL_ID = '000000000000000';    // Meta Pixel ID

    // Skip on admin/client pages
    var path = location.pathname.toLowerCase();
    if (path.indexOf('admin') !== -1 || path.indexOf('cliente') !== -1 || path.indexOf('login') !== -1) return;

    // Skip if IDs are placeholder
    var gaReady    = GA_ID.indexOf('XXXX') === -1;
    var pixelReady = PIXEL_ID.indexOf('0000') === -1;

    // ── Google Analytics 4 ──────────────────────────────────────
    if (gaReady) {
        var gaScript = document.createElement('script');
        gaScript.async = true;
        gaScript.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
        document.head.appendChild(gaScript);

        window.dataLayer = window.dataLayer || [];
        function gtag(){ window.dataLayer.push(arguments); }
        window.gtag = gtag;
        gtag('js', new Date());
        gtag('config', GA_ID, {
            page_path: location.pathname,
            anonymize_ip: true
        });
    }

    // ── Meta Pixel ──────────────────────────────────────────────
    if (pixelReady) {
        window.fbq = window.fbq || function(){
            (window.fbq.q = window.fbq.q || []).push(arguments);
        };
        window._fbq = window.fbq;
        window.fbq.loaded = true;
        window.fbq.version = '2.0';
        window.fbq.queue = [];

        var pixelScript = document.createElement('script');
        pixelScript.async = true;
        pixelScript.src = 'https://connect.facebook.net/en_US/fbevents.js';
        document.head.appendChild(pixelScript);

        window.fbq('init', PIXEL_ID);
        window.fbq('track', 'PageView');
    }

    // ── Conversion Events ───────────────────────────────────────
    // Track form submissions
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || !form.tagName || form.tagName !== 'FORM') return;

        // Contact form
        if (form.id === 'contactForm' || form.action.indexOf('formspree') !== -1) {
            if (gaReady && window.gtag) {
                window.gtag('event', 'generate_lead', {
                    event_category: 'engagement',
                    event_label: 'contact_form'
                });
            }
            if (pixelReady && window.fbq) {
                window.fbq('track', 'Lead');
            }
        }
    });

    // Track WhatsApp clicks
    document.addEventListener('click', function(e) {
        var link = e.target.closest ? e.target.closest('a') : null;
        if (!link) return;
        var href = link.href || '';

        if (href.indexOf('wa.me') !== -1 || href.indexOf('whatsapp') !== -1) {
            if (gaReady && window.gtag) {
                window.gtag('event', 'contact', {
                    event_category: 'engagement',
                    event_label: 'whatsapp_click',
                    value: 1
                });
            }
            if (pixelReady && window.fbq) {
                window.fbq('track', 'Contact');
            }
        }
    });

    // Track CTA button clicks
    document.addEventListener('click', function(e) {
        var btn = e.target.closest ? e.target.closest('.btn-primary, .cta-btn, [data-track]') : null;
        if (!btn) return;

        var label = btn.getAttribute('data-track') || btn.textContent.trim().substring(0, 50);
        if (gaReady && window.gtag) {
            window.gtag('event', 'cta_click', {
                event_category: 'engagement',
                event_label: label
            });
        }
    });
})();
