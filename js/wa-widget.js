/* ================================================================
   ProWay Lab — WhatsApp Floating Widget v1.0
   Adds a floating WhatsApp button with contextual pre-filled messages.
   Include in all public pages: <script src="/js/wa-widget.js" defer></script>
   ================================================================ */
(function(){
    'use strict';

    var PHONE = '573124904720';

    // Skip on admin/client portal pages
    var path = location.pathname.toLowerCase();
    if (path.indexOf('admin') !== -1 || path.indexOf('cliente') !== -1 || path.indexOf('login') !== -1) return;

    // Contextual messages per page
    var messages = {
        '/':                        'Hola ProWay Lab! Quiero informacion sobre sus servicios de produccion de contenido fitness.',
        '/index.html':              'Hola ProWay Lab! Quiero informacion sobre sus servicios de produccion de contenido fitness.',
        '/servicios.html':          'Hola ProWay Lab! Quiero cotizar un servicio de produccion de contenido.',
        '/metodo.html':             'Hola ProWay Lab! Me interesa conocer mas sobre su metodologia de trabajo.',
        '/contacto.html':           'Hola ProWay Lab! Quiero solicitar un diagnostico de marca gratuito.',
        '/portafolio.html':         'Hola ProWay Lab! Vi su portafolio y quiero cotizar un proyecto similar.',
        '/casos-de-exito.html':     'Hola ProWay Lab! Vi sus casos de exito y quiero resultados similares para mi marca.',
        '/blog/':                   'Hola ProWay Lab! Estuve leyendo su blog y quiero mas informacion.',
        '/portafolio-disenador-grafico.html': 'Hola ProWay Lab! Me interesa el servicio de diseno grafico para mi marca fitness.'
    };

    // Find message: exact match first, then prefix match for blog articles
    var msg = messages[path];
    if (!msg) {
        if (path.indexOf('/blog/') === 0) {
            msg = messages['/blog/'];
        } else {
            msg = 'Hola ProWay Lab! Quiero informacion sobre sus servicios.';
        }
    }

    var url = 'https://wa.me/' + PHONE + '?text=' + encodeURIComponent(msg);

    // Create floating button via safe DOM methods
    var btn = document.createElement('a');
    btn.href = url;
    btn.target = '_blank';
    btn.rel = 'noopener noreferrer';
    btn.setAttribute('aria-label', 'Contactar por WhatsApp');
    btn.id = 'pw-wa-float';

    // WhatsApp SVG icon — built via DOM, not innerHTML
    var svgNS = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 32 32');
    svg.setAttribute('width', '28');
    svg.setAttribute('height', '28');
    svg.setAttribute('fill', '#fff');
    var svgPath = document.createElementNS(svgNS, 'path');
    svgPath.setAttribute('d', 'M16.004 0h-.008C7.174 0 0 7.176 0 16.004c0 3.5 1.128 6.744 3.046 9.378L1.054 31.29l6.118-1.958A15.9 15.9 0 0 0 16.004 32C24.826 32 32 24.826 32 16.004 32 7.176 24.826 0 16.004 0zm9.342 22.616c-.394 1.108-2.302 2.12-3.212 2.196-.82.068-1.856.098-2.994-.19a27.4 27.4 0 0 1-2.71-.998c-4.768-2.064-7.88-6.884-8.118-7.204-.238-.318-1.944-2.588-1.944-4.936 0-2.35 1.23-3.508 1.666-3.986.436-.478.952-.598 1.27-.598.318 0 .636.002.914.016.294.016.688-.112 1.076.82.394.952 1.348 3.302 1.466 3.54.12.238.198.516.04.834-.16.318-.238.516-.478.794-.238.278-.502.622-.716.834-.238.238-.486.496-.208.972.278.478 1.234 2.034 2.65 3.296 1.818 1.62 3.352 2.122 3.83 2.36.478.238.756.198 1.034-.12.278-.318 1.194-1.39 1.512-1.868.318-.478.636-.398 1.074-.238.436.16 2.786 1.314 3.264 1.554.478.238.796.358.914.556.12.198.12 1.148-.274 2.256z');
    svg.appendChild(svgPath);
    btn.appendChild(svg);

    // Styles
    var s = btn.style;
    s.position = 'fixed';
    s.bottom = '24px';
    s.right = '24px';
    s.zIndex = '998';
    s.width = '56px';
    s.height = '56px';
    s.background = '#25D366';
    s.display = 'flex';
    s.alignItems = 'center';
    s.justifyContent = 'center';
    s.textDecoration = 'none';
    s.transition = 'transform 0.2s ease, box-shadow 0.2s ease';
    s.boxShadow = '0 4px 16px rgba(37,211,102,0.4)';

    btn.addEventListener('mouseenter', function(){
        this.style.transform = 'scale(1.08)';
        this.style.boxShadow = '0 6px 24px rgba(37,211,102,0.5)';
    });
    btn.addEventListener('mouseleave', function(){
        this.style.transform = 'scale(1)';
        this.style.boxShadow = '0 4px 16px rgba(37,211,102,0.4)';
    });

    // Tooltip
    var tip = document.createElement('span');
    tip.textContent = 'Escr\u00edbenos';
    var ts = tip.style;
    ts.position = 'absolute';
    ts.right = '68px';
    ts.top = '50%';
    ts.transform = 'translateY(-50%)';
    ts.background = '#111114';
    ts.color = '#fff';
    ts.fontFamily = "'Inter',sans-serif";
    ts.fontSize = '12px';
    ts.padding = '6px 12px';
    ts.whiteSpace = 'nowrap';
    ts.opacity = '0';
    ts.transition = 'opacity 0.25s ease';
    ts.pointerEvents = 'none';
    ts.border = '1px solid #252528';

    btn.appendChild(tip);
    btn.addEventListener('mouseenter', function(){ tip.style.opacity = '1'; });
    btn.addEventListener('mouseleave', function(){ tip.style.opacity = '0'; });

    // Adjust position if chat widget exists (avoid overlap)
    function adjustPosition(){
        var chatBtn = document.getElementById('pw-chat-toggle');
        if (chatBtn) {
            s.bottom = '90px';
        }
    }

    document.body.appendChild(btn);
    adjustPosition();
    // Re-check after chat widget may load
    setTimeout(adjustPosition, 2000);
})();
