/**
 * ProWay Lab — Chat Widget con KB de reglas
 * Widget flotante de soporte. Sin API keys, 100% offline con fallback a API.
 * Incluir con: <script src="/js/chat-widget.js" defer></script>
 *
 * SEGURIDAD: Todo contenido de usuario se inserta con textContent (nunca innerHTML).
 */
(function () {
  'use strict';

  /* ─── CONFIG ─────────────────────────────────────────────── */
  var PW = {
    brand:       'ProWay Lab',
    subhead:     'Asistente de Produccion',
    color:       '#00D9FF',
    bg:          '#0C0C0F',
    surface:     '#111114',
    surface2:    '#191920',
    border:      '#252528',
    gray:        '#A1A1AA',
    green:       '#00FF87',
    waLink:      'https://wa.me/573124904720?text=Hola%20ProWay!%20Quiero%20informacion%20sobre%20sus%20servicios',
    contactEmail:'info@prowaylab.com',
    storageKey:  'pw_chat_history',
    sessionKey:  'pw_chat_session',
    typingDelay: 900,
    aiEnabled:   true,
    aiEndpoint:  '/api/ai/chat',
  };

  /* ─── KNOWLEDGE BASE ─────────────────────────────────────── */
  var KB = [
    {
      tags: ['hola','buenos dias','buenas tardes','buenas noches','saludos','hey','hi','hello','buenas'],
      answer: 'Hola! Soy el asistente de ProWay Lab. Puedo ayudarte con informacion sobre nuestros servicios de produccion audiovisual para fitness. Que necesitas saber?',
      quick: ['Que servicios ofrecen?','Cuanto cuesta?','Como funciona?']
    },
    {
      tags: ['que es proway','proway','quienes son','que hacen','de que trata','a que se dedican','productora'],
      answer: 'ProWay Lab es una productora audiovisual especializada en fitness. Ayudamos a coaches y creadores a construir marcas profesionales con produccion de video, branding y estrategia de contenido.\n\nNo somos gym ni coaching — somos el equipo de produccion detras de las marcas fitness que crecen con metodologia.',
      quick: ['Que servicios ofrecen?','Ver precios','Como funciona?']
    },
    {
      tags: ['servicio','servicios','que ofrecen','produccion','contenido','oferta','video','edicion'],
      answer: 'Nuestros servicios:\n\n// PRODUCCION DE VIDEO\nReels, shorts, videos largos, showreels\n\n// BRANDING\nLogos, paletas, identidad visual completa\n\n// ESTRATEGIA DE CONTENIDO\nCalendarios, guiones, analisis de tendencias\n\n// GESTION DE MARCA\nPaquetes mensuales con produccion continua\n\nTodo orientado exclusivamente al nicho fitness.',
      quick: ['Ver precios','Ver portafolio','Contactar']
    },
    {
      tags: ['precio','costo','cuanto','cuanto cuesta','valor','tarifa','plan','paquete','inversion'],
      answer: 'Tenemos 4 opciones:\n\n// VIDEO INDIVIDUAL\nProyecto unico — cotizacion segun alcance\n\n// STARTER — $1.200.000 COP/mes\n4 reels/mes + edicion + calendario basico\n\n// GROWTH — $1.600.000 COP/mes\n8 videos + branding + estrategia\n\n// AUTHORITY — $2.200.000 COP/mes\n12 videos + marca completa + gestion + analytics\n\nQuieres cotizar?',
      quick: ['Que incluye Starter?','Que incluye Growth?','Que incluye Authority?']
    },
    {
      tags: ['starter','basico','1200','1.200','primer plan','empezar'],
      answer: 'STARTER — $1.200.000 COP/mes:\n\n>> 4 reels/shorts por mes\n>> Edicion profesional\n>> Calendario de publicacion basico\n>> 1 ronda de revisiones por video\n>> Entrega en formatos IG + TikTok\n\nIdeal para coaches que empiezan a profesionalizar su contenido.',
      quick: ['Que incluye Growth?','Quiero cotizar','Contactar']
    },
    {
      tags: ['growth','crecimiento','1600','1.600','intermedio','popular'],
      answer: 'GROWTH — $1.600.000 COP/mes:\n\n>> 8 videos/mes (reels + contenido largo)\n>> Branding basico (paleta + tipografia)\n>> Estrategia de contenido mensual\n>> Guiones optimizados para engagement\n>> 2 rondas de revisiones\n>> Analisis de metricas basico\n\nEl mas elegido — combina produccion con estrategia.',
      quick: ['Que incluye Authority?','Quiero cotizar','Contactar']
    },
    {
      tags: ['authority','premium','completo','2200','2.200','todo incluido','agencia'],
      answer: 'AUTHORITY — $2.200.000 COP/mes:\n\n>> 12 videos/mes (todos los formatos)\n>> Identidad de marca completa\n>> Estrategia de contenido + calendario\n>> Gestion de redes sociales\n>> Analytics mensual con reporte\n>> Guiones + copywriting\n>> Soporte prioritario\n\nPara coaches que quieren ser autoridad en su nicho.',
      quick: ['Quiero cotizar','Ver portafolio','Contactar']
    },
    {
      tags: ['metodo','proceso','como funciona','fases','pasos','metodologia','como trabajan'],
      answer: 'Trabajamos con el Metodo ProWay de 6 fases:\n\nF01 // DIAGNOSTICO — Analisis de tu marca y competencia\nF02 // ARQUITECTURA — Diseno de identidad y estrategia\nF03 // PLANIFICACION — Calendario, guiones, preproduccion\nF04 // PRODUCCION — Grabacion y edicion profesional\nF05 // DISTRIBUCION — Publicacion optimizada\nF06 // MEDICION — Resultados y ajustes\n\nCada fase tiene entregables claros y tiempos definidos.',
      quick: ['Ver precios','Quiero cotizar','Ver portafolio']
    },
    {
      tags: ['portafolio','ejemplo','trabajo','muestra','casos','resultados'],
      answer: 'Puedes ver nuestro portafolio completo en prowaylab.com/portafolio con ejemplos de produccion de reels, branding, thumbnails y casos de exito.',
      quick: ['Ver precios','Contactar','Como funciona?'],
      action: { label: '>> Ver portafolio', url: '/portafolio.html' }
    },
    {
      tags: ['tiempo','cuanto tarda','entrega','plazo','cuando','rapido','urgente','deadline'],
      answer: 'Tiempos de entrega:\n\n>> Video individual: 5-7 dias habiles\n>> Paquete mensual: entregas semanales\n>> Branding completo: 10-15 dias\n>> Proyecto de marca: 3-4 semanas\n\nRevisiones con turnaround de 48h.',
      quick: ['Ver precios','Quiero cotizar','Contactar']
    },
    {
      tags: ['formato','reel','reels','short','shorts','tiktok','youtube','instagram','vertical'],
      answer: 'Producimos para todas las plataformas:\n\n>> Instagram: Reels 9:16, carruseles, stories\n>> TikTok: Videos verticales optimizados\n>> YouTube: Shorts + videos largos 16:9\n>> LinkedIn: Contenido profesional\n\nCada video en los formatos correctos para cada plataforma.',
      quick: ['Ver precios','Quiero cotizar','Ver portafolio']
    },
    {
      tags: ['contacto','contactar','escribir','whatsapp','email','telefono','reunion','agendar','llamar'],
      answer: 'Contactanos:\n\n>> WhatsApp: +57 312 490 4720\n>> Email: info@prowaylab.com\n>> Web: prowaylab.com/contacto\n\nLa primera reunion de briefing es gratuita.',
      quick: ['Abrir WhatsApp','Ver precios','Como funciona?']
    },
    {
      tags: ['equipo','quien','quienes','fundador','experiencia','team'],
      answer: 'ProWay Lab es un equipo especializado en produccion audiovisual para fitness:\n\n>> Editores con experiencia en fitness content\n>> Disenadores especializados en marcas deportivas\n>> Estrategas con conocimiento del mercado LATAM\n\nEntendemos el nicho porque vivimos en el.',
      quick: ['Ver portafolio','Contactar','Ver precios']
    },
    {
      tags: ['donde','ubicacion','ciudad','pais','colombia','latam','remoto','presencial'],
      answer: 'Operamos desde Colombia con alcance en todo LATAM. Todo nuestro flujo es 100% remoto — trabajamos con coaches en Colombia, Mexico, Argentina, Chile y toda la region.',
      quick: ['Como funciona?','Contactar','Ver precios']
    },
    {
      tags: ['por que','diferencia','diferencial','mejor','ventaja','comparar','otro','agencia'],
      answer: 'Por que ProWay Lab?\n\n01 // ESPECIALIZACION — Solo fitness. Conocemos el nicho.\n02 // METODOLOGIA — 6 fases estructuradas.\n03 // RESULTADOS — Medimos todo. Analytics mensual.\n04 // LATAM — Contenido que conecta con tu audiencia.',
      quick: ['Ver portafolio','Ver precios','Contactar']
    },
    {
      tags: ['gracias','ok','perfecto','genial','listo','bye','adios','chao','hasta luego','entendido'],
      answer: 'Con gusto! Si necesitas mas informacion, estamos en WhatsApp. La primera reunion es gratis.',
      quick: ['Abrir WhatsApp','Ver precios']
    },
    {
      tags: ['__fallback__'],
      answer: 'No tengo esa informacion especifica, pero el equipo ProWay puede ayudarte. Quieres que te conectemos?',
      quick: ['Abrir WhatsApp','Ver servicios','Ver precios']
    }
  ];

  /* ─── NLP UTILS ─────────────────────────────────────────── */
  function normalize(str) {
    return str
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function tokenize(str) {
    return normalize(str).split(' ').filter(function(w){ return w.length > 2; });
  }

  function findBestMatch(input) {
    var tokens = tokenize(input);
    if (!tokens.length) return null;

    var best = null, bestScore = 0;

    for (var i = 0; i < KB.length; i++) {
      var entry = KB[i];
      if (entry.tags[0] === '__fallback__') continue;

      var score = 0;
      for (var j = 0; j < tokens.length; j++) {
        for (var k = 0; k < entry.tags.length; k++) {
          var tag = normalize(entry.tags[k]);
          if (tag.indexOf(tokens[j]) !== -1 || tokens[j].indexOf(tag) !== -1) {
            score += (tag === tokens[j]) ? 2 : 1;
          }
        }
      }
      var normalizedScore = score / Math.sqrt(entry.tags.length);
      if (normalizedScore > bestScore) {
        bestScore = normalizedScore;
        best = entry;
      }
    }

    return bestScore >= 0.5 ? best : KB[KB.length - 1];
  }

  /* ─── HISTORY ────────────────────────────────────────────── */
  function loadHistory() {
    try { return JSON.parse(localStorage.getItem(PW.storageKey)) || []; }
    catch(e) { return []; }
  }

  function saveHistory(h) {
    try { localStorage.setItem(PW.storageKey, JSON.stringify(h.slice(-40))); }
    catch(e) {}
  }

  /* ─── DOM HELPERS ────────────────────────────────────────── */
  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function(k) {
        if (k === 'style') {
          Object.assign(node.style, attrs[k]);
        } else if (k === 'class') {
          node.className = attrs[k];
        } else if (k === 'text') {
          node.textContent = attrs[k];
        } else {
          node.setAttribute(k, attrs[k]);
        }
      });
    }
    if (children) {
      children.forEach(function(c) { if (c) node.appendChild(c); });
    }
    return node;
  }

  /* ─── BUILD WIDGET DOM ───────────────────────────────────── */
  function buildWidget() {
    function svgEl(paths, w, h) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('width', w || 24);
      svg.setAttribute('height', h || 24);
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none');
      svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '2');
      svg.setAttribute('stroke-linecap', 'round');
      svg.setAttribute('stroke-linejoin', 'round');
      paths.forEach(function(d) {
        var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('d', d);
        svg.appendChild(p);
      });
      return svg;
    }

    function svgLines(lines, w, h) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('width', w || 24);
      svg.setAttribute('height', h || 24);
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none');
      svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '2.5');
      svg.setAttribute('stroke-linecap', 'round');
      lines.forEach(function(coords) {
        var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', coords[0]); line.setAttribute('y1', coords[1]);
        line.setAttribute('x2', coords[2]); line.setAttribute('y2', coords[3]);
        svg.appendChild(line);
      });
      return svg;
    }

    function svgSend() {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('width', 18); svg.setAttribute('height', 18);
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor');
      svg.setAttribute('stroke-width', '2'); svg.setAttribute('stroke-linecap', 'round');
      var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('x1','22'); line.setAttribute('y1','2');
      line.setAttribute('x2','11'); line.setAttribute('y2','13');
      var poly = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
      poly.setAttribute('points','22 2 15 22 11 13 2 9 22 2');
      svg.appendChild(line); svg.appendChild(poly);
      return svg;
    }

    var closeSvgTrigger = svgLines([[18,6,6,18],[6,6,18,18]], 22, 22);
    closeSvgTrigger.id = 'pw-icon-close';
    closeSvgTrigger.style.display = 'none';

    var openSvg = svgEl(['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z']);
    openSvg.id = 'pw-icon-open';

    var notifBadge = el('span', { id: 'pw-notif', style: { display: 'none' } });

    var trigger = el('button', { id: 'pw-trigger', 'aria-label': 'Abrir chat ProWay' }, [
      openSvg, closeSvgTrigger, notifBadge
    ]);

    /* Header */
    var avatar = el('div', { id: 'pw-avatar', text: 'P' });
    var brandDiv = el('div', { id: 'pw-brand', text: PW.brand });
    var dot = el('span', { id: 'pw-dot' });
    var statusDiv = el('div', { id: 'pw-status' }, [dot]);
    statusDiv.appendChild(document.createTextNode(PW.subhead));
    var headerInfo = el('div', { id: 'pw-header-info' }, [brandDiv, statusDiv]);
    var closeSvgHeader = svgLines([[18,6,6,18],[6,6,18,18]], 16, 16);
    var closeBtn = el('button', { id: 'pw-close-btn', 'aria-label': 'Cerrar chat' }, [closeSvgHeader]);
    var header = el('div', { id: 'pw-header' }, [avatar, headerInfo, closeBtn]);

    /* Messages + quick */
    var msgsDiv = el('div', { id: 'pw-msgs' });
    var quickDiv = el('div', { id: 'pw-quick' });

    /* Footer */
    var inputEl = el('input', {
      id: 'pw-input', type: 'text',
      placeholder: 'Pregunta sobre nuestros servicios...',
      autocomplete: 'off', maxlength: '200'
    });
    var sendBtn = el('button', { id: 'pw-send', 'aria-label': 'Enviar' }, [svgSend()]);
    var footer = el('div', { id: 'pw-footer' }, [inputEl, sendBtn]);

    /* Powered */
    var poweredA = el('a', { href: 'mailto:' + PW.contactEmail, text: PW.contactEmail });
    var powered = el('div', { id: 'pw-powered' });
    powered.appendChild(document.createTextNode('Powered by ProWay Lab // '));
    powered.appendChild(poweredA);

    /* Window */
    var win = el('div', {
      id: 'pw-window',
      role: 'dialog',
      'aria-label': 'Chat ProWay Lab',
      'aria-hidden': 'true'
    }, [header, msgsDiv, quickDiv, footer, powered]);

    /* Root */
    var root = el('div', { id: 'pw-chat-root' }, [trigger, win]);
    document.body.appendChild(root);
  }

  /* ─── STYLES ─────────────────────────────────────────────── */
  function injectStyles() {
    var css = [
      '#pw-chat-root *{box-sizing:border-box;margin:0;padding:0}',
      '#pw-chat-root{position:fixed;bottom:24px;right:24px;z-index:99999;font-family:Inter,-apple-system,sans-serif}',
      '#pw-trigger{width:56px;height:56px;border-radius:0;background:' + PW.color + ';border:2px solid ' + PW.border + ';cursor:pointer;color:#000;display:flex;align-items:center;justify-content:center;box-shadow:none;transition:border-color .1s linear;position:relative}',
      '#pw-trigger:hover{border-color:' + PW.color + '}',
      '#pw-trigger:active{opacity:.9}',
      '#pw-notif{position:absolute;top:-3px;right:-3px;background:' + PW.green + ';color:#000;width:18px;height:18px;border-radius:50%;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid ' + PW.bg + '}',
      '#pw-window{position:absolute;bottom:70px;right:0;width:340px;max-height:520px;background:' + PW.surface + ';border:2px solid ' + PW.border + ';border-radius:0;box-shadow:0 16px 48px rgba(0,0,0,.6);display:flex;flex-direction:column;overflow:hidden;opacity:0;transform:translateY(12px) scale(.97);pointer-events:none;transition:opacity .1s linear,transform .1s linear}',
      '#pw-window.open{opacity:1;transform:translateY(0) scale(1);pointer-events:all}',
      '#pw-header{display:flex;align-items:center;gap:10px;padding:14px 16px;background:' + PW.surface2 + ';border-bottom:1px solid ' + PW.border + ';flex-shrink:0}',
      '#pw-avatar{width:36px;height:36px;border-radius:0;background:' + PW.color + ';display:flex;align-items:center;justify-content:center;font-family:"Roboto Mono",monospace;font-size:16px;font-weight:700;color:#000;flex-shrink:0}',
      '#pw-brand{font-family:"Montserrat",sans-serif;font-size:14px;font-weight:700;color:#fff;letter-spacing:.04em;text-transform:uppercase}',
      '#pw-status{font-size:11px;color:' + PW.gray + ';display:flex;align-items:center;gap:5px}',
      '#pw-dot{width:7px;height:7px;border-radius:50%;background:' + PW.green + ';animation:pw-pulse 2s ease-in-out infinite;flex-shrink:0}',
      '#pw-close-btn{margin-left:auto;background:none;border:none;color:' + PW.gray + ';cursor:pointer;padding:4px;border-radius:0;transition:color .1s linear;display:flex}',
      '#pw-close-btn:hover{color:#fff}',
      '#pw-msgs{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth}',
      '#pw-msgs::-webkit-scrollbar{width:4px}',
      '#pw-msgs::-webkit-scrollbar-track{background:transparent}',
      '#pw-msgs::-webkit-scrollbar-thumb{background:' + PW.border + ';border-radius:0}',
      '.pw-msg{display:flex;gap:8px;animation:pw-slide-in .2s ease}',
      '.pw-msg.bot{align-items:flex-start}',
      '.pw-msg.user{flex-direction:row-reverse}',
      '.pw-msg-ico{width:28px;height:28px;border-radius:0;background:' + PW.color + ';flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#000;font-family:"Roboto Mono",monospace;font-weight:700;margin-top:2px}',
      '.pw-bubble{max-width:80%;padding:9px 13px;border-radius:0;font-size:13px;line-height:1.5;white-space:pre-wrap;word-break:break-word}',
      '.pw-msg.bot .pw-bubble{background:' + PW.surface2 + ';color:#e0e0e0}',
      '.pw-msg.user .pw-bubble{background:' + PW.color + ';color:#000}',
      '.pw-action-btn{display:inline-block;margin-top:8px;padding:6px 12px;border-radius:0;background:' + PW.color + ';color:#000;font-size:12px;font-weight:600;text-decoration:none;transition:opacity .1s linear}',
      '.pw-action-btn:hover{opacity:.85}',
      '.pw-typing-dot{width:6px;height:6px;border-radius:50%;background:' + PW.gray + ';animation:pw-bounce 1.2s ease-in-out infinite;display:inline-block}',
      '.pw-typing-wrap{display:flex;align-items:center;gap:4px;padding:4px 0}',
      '#pw-quick{padding:0 12px 10px;display:flex;flex-wrap:wrap;gap:6px;flex-shrink:0}',
      '.pw-qr{padding:5px 11px;border:1px solid ' + PW.border + ';border-radius:0;background:none;color:' + PW.gray + ';font-size:11px;cursor:pointer;transition:border-color .1s linear,color .1s linear;white-space:nowrap}',
      '.pw-qr:hover{border-color:' + PW.color + ';color:#fff}',
      '#pw-footer{display:flex;align-items:center;gap:8px;padding:10px 12px;border-top:1px solid ' + PW.border + ';background:' + PW.surface2 + ';flex-shrink:0}',
      '#pw-input{flex:1;background:' + PW.bg + ';border:1px solid ' + PW.border + ';border-radius:0;padding:8px 12px;color:#fff;font-size:13px;outline:none;transition:border-color .1s linear;font-family:inherit}',
      '#pw-input:focus{border-color:rgba(0,217,255,.5)}',
      '#pw-input::placeholder{color:' + PW.gray + '}',
      '#pw-send{width:36px;height:36px;background:' + PW.color + ';border:none;border-radius:0;color:#000;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .1s linear}',
      '#pw-send:hover{opacity:.85}',
      '#pw-powered{text-align:center;font-size:10px;color:' + PW.gray + ';padding:6px;border-top:1px solid ' + PW.border + ';flex-shrink:0}',
      '#pw-powered a{color:' + PW.gray + ';text-decoration:none}',
      '#pw-powered a:hover{color:#fff}',
      '@keyframes pw-pulse{0%,100%{opacity:1}50%{opacity:.4}}',
      '@keyframes pw-bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}',
      '@keyframes pw-slide-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}',
      '@media(max-width:400px){#pw-window{width:calc(100vw - 32px);right:-4px}#pw-chat-root{right:16px;bottom:16px}}'
    ].join('');

    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
  }

  /* ─── RENDER ─────────────────────────────────────────────── */
  var msgsEl, quickEl, inputEl;
  var history = [];
  var isOpen = false;

  function scrollBottom() { msgsEl.scrollTop = msgsEl.scrollHeight; }

  function renderMsg(role, text, action) {
    var wrap = document.createElement('div');
    wrap.className = 'pw-msg ' + role;

    if (role === 'bot') {
      var ico = document.createElement('div');
      ico.className = 'pw-msg-ico';
      ico.textContent = 'P';
      wrap.appendChild(ico);
    }

    var bubble = document.createElement('div');
    bubble.className = 'pw-bubble';
    bubble.textContent = text;

    if (action) {
      var br = document.createElement('br');
      var a = document.createElement('a');
      a.href = action.url;
      a.className = 'pw-action-btn';
      a.textContent = action.label;
      bubble.appendChild(br);
      bubble.appendChild(a);
    }

    wrap.appendChild(bubble);
    msgsEl.appendChild(wrap);
    scrollBottom();
  }

  function showTyping() {
    var wrap = document.createElement('div');
    wrap.className = 'pw-msg bot';
    wrap.id = 'pw-typing-wrap-el';

    var ico = document.createElement('div');
    ico.className = 'pw-msg-ico';
    ico.textContent = 'P';

    var bubble = document.createElement('div');
    bubble.className = 'pw-bubble';

    var dotsWrap = document.createElement('div');
    dotsWrap.className = 'pw-typing-wrap';

    for (var i = 0; i < 3; i++) {
      var dot = document.createElement('span');
      dot.className = 'pw-typing-dot';
      dot.style.animationDelay = (i * 0.2) + 's';
      dotsWrap.appendChild(dot);
    }

    bubble.appendChild(dotsWrap);
    wrap.appendChild(ico);
    wrap.appendChild(bubble);
    msgsEl.appendChild(wrap);
    scrollBottom();
  }

  function removeTyping() {
    var el = document.getElementById('pw-typing-wrap-el');
    if (el) el.remove();
  }

  function setQuickReplies(list) {
    quickEl.textContent = '';
    if (!list || !list.length) return;
    list.forEach(function(label) {
      var btn = document.createElement('button');
      btn.className = 'pw-qr';
      btn.textContent = label;
      btn.addEventListener('click', function() { handleInput(label); });
      quickEl.appendChild(btn);
    });
  }

  /* ─── INPUT HANDLER ──────────────────────────────────────── */
  function handleInput(text) {
    text = String(text || '').trim();
    if (!text) return;
    inputEl.value = '';

    renderMsg('user', text);
    quickEl.textContent = '';
    history.push({ role: 'user', text: text });

    var norm = text.toLowerCase();

    // Special actions
    var specials = {
      'abrir whatsapp': function() { respondMsg('Te conecto con el equipo ProWay en WhatsApp:', { label: '>> Abrir WhatsApp', url: PW.waLink }, ['Ver precios','Ver servicios']); },
      'quiero cotizar': function() { respondMsg('Agenda tu reunion de briefing gratuita por WhatsApp:', { label: '>> Abrir WhatsApp', url: PW.waLink }, ['Ver precios','Como funciona?']); },
      'ver precios': function() { respondMsg('Aqui puedes ver todos los planes y precios:', { label: '>> Ver servicios', url: '/servicios.html' }, ['Contactar','Como funciona?']); },
      'ver servicios': function() { respondMsg('Aqui estan nuestros servicios:', { label: '>> Ver servicios', url: '/servicios.html' }, ['Ver precios','Contactar']); },
      'ver portafolio': function() { respondMsg('Mira nuestro trabajo:', { label: '>> Ver portafolio', url: '/portafolio.html' }, ['Ver precios','Contactar']); },
      'contactar': function() { respondMsg('Contactanos directamente:', { label: '>> WhatsApp', url: PW.waLink }, ['Ver precios','Ver servicios']); }
    };

    var matched = null;
    Object.keys(specials).forEach(function(k) {
      if (norm.indexOf(k) !== -1) matched = specials[k];
    });

    if (matched) {
      setTimeout(function() {
        showTyping();
        setTimeout(function() { removeTyping(); matched(); }, PW.typingDelay);
      }, 80);
    } else {
      var match = findBestMatch(text);
      var isFallback = match && match.tags && match.tags[0] === '__fallback__';

      if (isFallback && PW.aiEnabled) {
        showTyping();
        callAIBackend(text);
      } else {
        setTimeout(function() {
          showTyping();
          setTimeout(function() {
            removeTyping();
            if (match) {
              renderMsg('bot', match.answer, match.action || null);
              setQuickReplies(match.quick || []);
              history.push({ role: 'bot', text: match.answer });
            }
            saveHistory(history);
          }, PW.typingDelay + Math.floor(Math.random() * 200));
        }, 80);
      }
    }
    saveHistory(history);
  }

  function respondMsg(text, action, quick) {
    renderMsg('bot', text, action);
    setQuickReplies(quick || []);
    history.push({ role: 'bot', text: text });
    saveHistory(history);
  }

  /* ─── AI BACKEND ─────────────────────────────────────────── */
  function getSessionId() {
    var sid = null;
    try { sid = localStorage.getItem(PW.sessionKey); } catch(e) {}
    if (!sid) {
      sid = 'pw_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);
      try { localStorage.setItem(PW.sessionKey, sid); } catch(e) {}
    }
    return sid;
  }

  function callAIBackend(userText) {
    fetch(PW.aiEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: userText, session_id: getSessionId() })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      removeTyping();
      if (data.ok && data.response) {
        renderMsg('bot', data.response);
        setQuickReplies(['Ver precios', 'Contactar']);
        history.push({ role: 'bot', text: data.response });
        if (data.session_id) {
          try { localStorage.setItem(PW.sessionKey, data.session_id); } catch(e) {}
        }
      } else {
        var fallbackMsg = data.error || 'No pude procesar tu pregunta. Contactanos por WhatsApp.';
        renderMsg('bot', fallbackMsg);
        setQuickReplies(['Abrir WhatsApp', 'Ver precios']);
        history.push({ role: 'bot', text: fallbackMsg });
      }
      saveHistory(history);
    })
    .catch(function() {
      removeTyping();
      var fallback = KB[KB.length - 1];
      renderMsg('bot', fallback.answer);
      setQuickReplies(fallback.quick || []);
      history.push({ role: 'bot', text: fallback.answer });
      saveHistory(history);
    });
  }

  /* ─── OPEN / CLOSE ───────────────────────────────────────── */
  function openChat() {
    isOpen = true;
    document.getElementById('pw-window').classList.add('open');
    document.getElementById('pw-window').setAttribute('aria-hidden', 'false');
    document.getElementById('pw-icon-open').style.display = 'none';
    document.getElementById('pw-icon-close').style.display = 'block';
    document.getElementById('pw-notif').style.display = 'none';

    if (!msgsEl.children.length) {
      setTimeout(function() {
        showTyping();
        setTimeout(function() {
          removeTyping();
          renderMsg('bot', 'Hola! Soy el asistente de ProWay Lab.\nEstoy aqui para responder tus preguntas sobre produccion audiovisual para fitness.');
          setQuickReplies(['Que servicios ofrecen?','Cuanto cuesta?','Como funciona?']);
        }, 600);
      }, 100);
    }
    setTimeout(function() { inputEl.focus(); }, 300);
  }

  function closeChat() {
    isOpen = false;
    document.getElementById('pw-window').classList.remove('open');
    document.getElementById('pw-window').setAttribute('aria-hidden', 'true');
    document.getElementById('pw-icon-open').style.display = 'block';
    document.getElementById('pw-icon-close').style.display = 'none';
  }

  /* ─── INIT ───────────────────────────────────────────────── */
  function init() {
    // Skip on admin and client pages
    var path = window.location.pathname;
    if (path.indexOf('admin') !== -1 || path.indexOf('cliente') !== -1) return;

    injectStyles();
    buildWidget();

    msgsEl  = document.getElementById('pw-msgs');
    quickEl = document.getElementById('pw-quick');
    inputEl = document.getElementById('pw-input');

    history = loadHistory();
    if (history.length) {
      history.slice(-10).forEach(function(m) { renderMsg(m.role, m.text); });
    }

    document.getElementById('pw-trigger').addEventListener('click', function() {
      isOpen ? closeChat() : openChat();
    });
    document.getElementById('pw-close-btn').addEventListener('click', closeChat);
    inputEl.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleInput(inputEl.value); }
    });
    document.getElementById('pw-send').addEventListener('click', function() { handleInput(inputEl.value); });

    // Show notification badge after 5s on first visit
    setTimeout(function() {
      if (!isOpen && !history.length) {
        var notif = document.getElementById('pw-notif');
        notif.textContent = '1';
        notif.style.display = 'flex';
      }
    }, 5000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
