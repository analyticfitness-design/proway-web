
    (function() {
        var auth = localStorage.getItem('pw_admin_auth');
        if (!auth) { window.location.href = 'login.html'; }
    }());



    /* ─── AUTH & USER DISPLAY ─────────────────────────────── */
    var storedUser = localStorage.getItem('pw_admin_user') || 'admin';

    /* Set username in sidebar */
    (function() {
        var userEl   = document.getElementById('adminUser');
        var avatarEl = document.getElementById('userAvatarLetter');
        if (userEl)   { userEl.textContent   = storedUser; }
        if (avatarEl) { avatarEl.textContent = storedUser.charAt(0).toUpperCase(); }
    }());

    /* Logout */
    document.getElementById('logoutBtn').addEventListener('click', function() {
        localStorage.removeItem('pw_admin_auth');
        localStorage.removeItem('pw_admin_user');
        localStorage.removeItem('pw_admin_keep');
        window.location.href = 'login.html';
    });

    /* ─── DATE in topbar ─────────────────────────────────── */
    (function() {
        var el = document.getElementById('topbarDate');
        if (!el) { return; }
        var d   = new Date();
        var day = String(d.getDate()).padStart(2, '0');
        var mon = String(d.getMonth() + 1).padStart(2, '0');
        var yr  = d.getFullYear();
        el.textContent = day + '/' + mon + '/' + yr;
    }());

    /* ─── NAVIGATION ─────────────────────────────────────── */
    var SECTION_LABELS = {
        dashboard:  'Dashboard',
        leads:      'Leads / Solicitudes',
        clientes:   'Clientes Activos',
        pagos:      'Pagos',
        contenido:  'Contenido / Proyectos',
        blog:       'Blog',
        mensajes:   'Mensajes',
        analytics:  'Analytics',
        config:     'Configuracion'
    };

    function navigateTo(sectionId) {
        /* Update sections */
        var views = document.querySelectorAll('.section-view');
        for (var i = 0; i < views.length; i++) {
            views[i].classList.remove('active');
        }
        var target = document.getElementById('sec-' + sectionId);
        if (target) { target.classList.add('active'); }

        /* Update sidebar items */
        var items = document.querySelectorAll('.sidebar-item');
        for (var j = 0; j < items.length; j++) {
            items[j].classList.remove('active');
            items[j].removeAttribute('aria-current');
            if (items[j].getAttribute('data-section') === sectionId) {
                items[j].classList.add('active');
                items[j].setAttribute('aria-current', 'page');
            }
        }

        /* Update breadcrumb */
        var bc = document.getElementById('breadcrumbLabel');
        if (bc) { bc.textContent = SECTION_LABELS[sectionId] || sectionId; }

        /* Close sidebar on mobile */
        if (window.innerWidth <= 900) {
            closeSidebar();
        }
    }

    /* Bind sidebar item clicks */
    var sidebarItems = document.querySelectorAll('.sidebar-item');
    for (var si = 0; si < sidebarItems.length; si++) {
        (function(item) {
            item.addEventListener('click', function() {
                navigateTo(item.getAttribute('data-section'));
            });
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    navigateTo(item.getAttribute('data-section'));
                }
            });
        }(sidebarItems[si]));
    }

    /* ─── MOBILE SIDEBAR ─────────────────────────────────── */
    var sidebar  = document.getElementById('adminSidebar');
    var overlay  = document.getElementById('sidebarOverlay');
    var menuBtn  = document.getElementById('menuToggle');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        menuBtn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    menuBtn.addEventListener('click', function() {
        if (sidebar.classList.contains('open')) { closeSidebar(); }
        else { openSidebar(); }
    });

    overlay.addEventListener('click', closeSidebar);

    /* ─── BAR CHART (CSS pure) ───────────────────────────── */
    (function() {
        var chartEl = document.getElementById('barChart');
        if (!chartEl) { return; }

        var data = [
            { label: 'V.Esencial', value: 8,  max: 12 },
            { label: 'V.Pro',      value: 12, max: 12 },
            { label: 'V.Elite',    value: 7,  max: 12 },
            { label: 'Starter',    value: 5,  max: 12 },
            { label: 'Growth',     value: 10, max: 12 },
            { label: 'Authority',  value: 5,  max: 12 }
        ];

        var chartHeight = 128; /* px available for bars */

        data.forEach(function(d) {
            var barH   = Math.round((d.value / d.max) * chartHeight);

            var wrap = document.createElement('div');
            wrap.className = 'bar-wrap';

            var valLabel = document.createElement('div');
            valLabel.className = 'bar-value';
            valLabel.textContent = d.value;

            var track = document.createElement('div');
            track.className = 'bar-track';
            track.style.height = chartHeight + 'px';
            track.setAttribute('role', 'img');
            track.setAttribute('aria-label', d.label + ': ' + d.value + ' leads');

            var fill = document.createElement('div');
            fill.className = 'bar-fill';
            fill.style.height = '0px';

            /* Animate bars on load */
            setTimeout(function() {
                fill.style.height = barH + 'px';
            }, 120);

            var lblEl = document.createElement('div');
            lblEl.className = 'bar-label';
            lblEl.textContent = d.label;

            track.appendChild(fill);
            track.appendChild(lblEl);
            wrap.appendChild(valLabel);
            wrap.appendChild(track);
            chartEl.appendChild(wrap);
        });
    }());

    /* ─── LEADS FILTER & SEARCH ─────────────────────────── */
    function filterLeadsTable() {
        var searchVal  = document.getElementById('leadsSearch').value.toLowerCase().trim();
        var filterVal  = document.getElementById('leadsFilter').value;
        var rows       = document.querySelectorAll('#leadsTbody tr');

        rows.forEach(function(row) {
            var nombre = row.getAttribute('data-nombre') || '';
            var estado = row.getAttribute('data-estado') || '';

            var matchSearch = !searchVal || nombre.toLowerCase().indexOf(searchVal) !== -1;
            var matchFilter = !filterVal || estado === filterVal;

            if (matchSearch && matchFilter) {
                row.classList.remove('row-hidden');
            } else {
                row.classList.add('row-hidden');
            }
        });
    }

    document.getElementById('leadsSearch').addEventListener('input', filterLeadsTable);
    document.getElementById('leadsFilter').addEventListener('change', filterLeadsTable);

    /* ─── CSV EXPORT ────────────────────────────────────── */
    function exportTableToCSV(tbodyId, filename) {
        var rows    = document.querySelectorAll('#' + tbodyId + ' tr:not(.row-hidden)');
        var csvRows = ['Fecha,Nombre,Servicio,Estado'];

        rows.forEach(function(row) {
            var cells = row.querySelectorAll('td');
            if (cells.length >= 4) {
                var fecha    = cells[0].textContent.trim();
                var nombre   = '"' + cells[1].textContent.trim() + '"';
                var servicio = '"' + cells[2].textContent.trim() + '"';
                /* Get badge text (estado) from span.badge */
                var badgeEl  = cells[3].querySelector('.badge');
                var estado   = badgeEl ? badgeEl.textContent.trim() : cells[3].textContent.trim();
                csvRows.push([fecha, nombre, servicio, '"' + estado + '"'].join(','));
            }
        });

        var csvContent = csvRows.join('\r\n');
        var blob       = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var url        = URL.createObjectURL(blob);
        var link       = document.createElement('a');
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    document.getElementById('leadsExportBtn').addEventListener('click', function() {
        exportTableToCSV('leadsTbody', 'proway-leads-' + new Date().toISOString().slice(0,10) + '.csv');
    });

    document.getElementById('dashExportBtn').addEventListener('click', function() {
        exportTableToCSV('dashTbody', 'proway-solicitudes-recientes.csv');
    });

    /* ─── API INTEGRATION ────────────────────────────────────
       Carga datos reales del backend. Si la API no responde,
       los datos demo del HTML se mantienen sin cambios.
       ──────────────────────────────────────────────────────── */

    function apiBearerFetch(path) {
        var token = localStorage.getItem('pw_admin_auth') || '';
        return fetch('/api' + path, {
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json'
            }
        });
    }

    function buildClientCard(c) {
        var initial     = (c.name || '?').charAt(0).toUpperCase();
        var planDisplay = (c.plan_type || '').replace(/_/g, ' ').toUpperCase();
        var startDate   = c.created_at ? c.created_at.substring(0, 10).split('-').reverse().join('/') : '—';

        var card = document.createElement('div');
        card.className = 'client-card';

        // Header
        var header = document.createElement('div'); header.className = 'client-card-header';
        var avatar = document.createElement('div'); avatar.className = 'client-avatar';
        avatar.textContent = initial;
        var nameWrap = document.createElement('div');
        var nameEl = document.createElement('div'); nameEl.className = 'client-name'; nameEl.textContent = c.name || '—';
        var svcEl  = document.createElement('div'); svcEl.className = 'client-service'; svcEl.textContent = planDisplay;
        nameWrap.appendChild(nameEl); nameWrap.appendChild(svcEl);
        header.appendChild(avatar); header.appendChild(nameWrap);

        // Meta rows
        var meta = document.createElement('div'); meta.className = 'client-meta';
        [
            ['EMAIL',      c.email || '—'],
            ['EMPRESA',    c.company || '—'],
            ['PROYECTOS',  (c.active_projects || 0) + ' activos / ' + (c.total_projects || 0) + ' total'],
            ['INICIO',     startDate]
        ].forEach(function(pair) {
            var row = document.createElement('div'); row.className = 'client-meta-row';
            var lbl = document.createElement('span'); lbl.className = 'client-meta-label'; lbl.textContent = pair[0];
            var val = document.createElement('span'); val.className = 'client-meta-value'; val.textContent = pair[1];
            row.appendChild(lbl); row.appendChild(val); meta.appendChild(row);
        });

        card.appendChild(header); card.appendChild(meta);

        // WhatsApp button (solo si tiene telefono)
        if (c.phone) {
            var wa = document.createElement('a');
            wa.className = 'btn-wa-client';
            wa.href      = 'https://wa.me/' + String(c.phone).replace(/\D/g, '');
            wa.target    = '_blank'; wa.rel = 'noopener noreferrer';
            var waIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            waIcon.setAttribute('width', '13'); waIcon.setAttribute('height', '13');
            waIcon.setAttribute('viewBox', '0 0 24 24'); waIcon.setAttribute('fill', 'currentColor');
            waIcon.setAttribute('aria-hidden', 'true');
            // path data hardcoded (no user data)
            var waPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            waPath.setAttribute('d', 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z');
            waIcon.appendChild(waPath);
            wa.appendChild(waIcon);
            var waText = document.createTextNode(' Contactar por WhatsApp');
            wa.appendChild(waText);
            card.appendChild(wa);
        }

        return card;
    }

    function loadLiveClients() {
        apiBearerFetch('/admin/clients')
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function(clients) {
                if (!clients || !clients.length) return;

                var activeClients = clients.filter(function(c) { return c.status === 'activo'; });
                if (!activeClients.length) return;

                var grid = document.querySelector('#sec-clientes .clients-grid');
                if (!grid) return;
                grid.textContent = '';
                activeClients.forEach(function(c) { grid.appendChild(buildClientCard(c)); });

                // Update sidebar count
                var countEl = document.querySelector('.sidebar-item[data-section="clientes"] .sidebar-item-count');
                if (countEl) countEl.textContent = activeClients.length;

                // Update subtitle
                var sub = document.querySelector('#sec-clientes .section-subtitle');
                if (sub) sub.textContent = activeClients.length + ' CLIENTES ACTIVOS EN GESTION';
            })
            .catch(function() {}); // Silently fail — se mantiene HTML demo
    }

    function loadLiveProjects() {
        apiBearerFetch('/admin/projects')
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function(projects) {
                if (!projects || !projects.length) return;

                var section = document.getElementById('sec-contenido');
                if (!section) return;

                // Reemplazar el "coming soon" con una tabla real
                var comingSoon = section.querySelector('.coming-soon');
                if (comingSoon) { comingSoon.style.display = 'none'; }

                // Crear tabla si no existe aún
                var existingTable = section.querySelector('table.projects-table');
                if (!existingTable) {
                    var table = document.createElement('table');
                    table.className = 'projects-table';
                    table.style.cssText = 'width:100%;border-collapse:collapse;font-size:13px;';

                    var thead = document.createElement('thead');
                    var hr = document.createElement('tr');
                    ['Cliente', 'Proyecto', 'Tipo', 'Estado', 'Deadline', 'Entregables'].forEach(function(h) {
                        var th = document.createElement('th');
                        th.textContent = h;
                        th.style.cssText = 'padding:10px 12px;text-align:left;font-family:"JetBrains Mono",monospace;font-size:9px;letter-spacing:.25em;text-transform:uppercase;color:var(--dark);border-bottom:1px solid var(--border);white-space:nowrap;';
                        hr.appendChild(th);
                    });
                    thead.appendChild(hr);

                    var tbody = document.createElement('tbody');
                    tbody.id = 'proyectosTbody';

                    table.appendChild(thead);
                    table.appendChild(tbody);
                    section.appendChild(table);
                }

                var tbody = document.getElementById('proyectosTbody');
                if (!tbody) return;
                tbody.textContent = '';

                var statusMap = {
                    'en_progreso': ['EN PROGRESO', 'badge-process'],
                    'revision':    ['REVISION',    'badge-process'],
                    'entregado':   ['ENTREGADO',   'badge-active'],
                    'pendiente':   ['PENDIENTE',   'badge-new'],
                    'facturado':   ['FACTURADO',   'badge-active'],
                    'pagado':      ['PAGADO',       'badge-closed']
                };

                projects.forEach(function(p) {
                    var tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid var(--border)';

                    var cells = [
                        p.client_name   || '—',
                        p.title         || p.description || '—',
                        (p.type || '').replace(/_/g, ' ').toUpperCase() || '—',
                        null, // badge
                        p.deadline ? p.deadline.substring(0, 10).split('-').reverse().join('/') : '—',
                        String(p.deliverables_count || 0)
                    ];

                    cells.forEach(function(val, i) {
                        var td = document.createElement('td');
                        td.style.cssText = 'padding:10px 12px;vertical-align:middle;';

                        if (i === 3) { // Status badge
                            var statusKey  = p.status || 'pendiente';
                            var statusData = statusMap[statusKey] || [statusKey.toUpperCase(), 'badge-new'];
                            var badge = document.createElement('span');
                            badge.className = 'badge ' + statusData[1];
                            badge.textContent = statusData[0];
                            td.appendChild(badge);
                        } else {
                            td.textContent = val;
                            if (i === 1) { td.style.fontWeight = '500'; }
                            if (i === 0 || i === 4) { td.style.cssText += 'color:var(--gray);font-family:"JetBrains Mono",monospace;font-size:11px;'; }
                        }

                        tr.appendChild(td);
                    });

                    tbody.appendChild(tr);
                });

                // Actualizar contador en sidebar
                var projCount = document.querySelector('.sidebar-item[data-section="contenido"] .sidebar-item-count');
                if (projCount) projCount.textContent = projects.length;

                // Actualizar subtítulo
                var sub = section.querySelector('.section-subtitle');
                if (sub) sub.textContent = projects.length + ' PROYECTOS EN TOTAL';
            })
            .catch(function() {}); // Silently fail
    }

    function loadLiveLeads() {
        // Leads = clientes con status 'prospecto'
        apiBearerFetch('/admin/clients')
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function(clients) {
                if (!clients || !clients.length) return;

                var prospectos = clients.filter(function(c) { return c.status === 'prospecto'; });
                if (!prospectos.length) return;

                var tbody = document.getElementById('leadsTbody');
                var dashTbody = document.getElementById('dashTbody');
                if (!tbody) return;

                // Limpiar filas demo y reemplazar con datos reales
                tbody.textContent = '';

                prospectos.forEach(function(c) {
                    var dateStr = c.created_at ? c.created_at.substring(0, 10).split('-').reverse().join('/') : '—';
                    var plan    = (c.plan_type || '').replace(/_/g, ' ');
                    plan = plan.charAt(0).toUpperCase() + plan.slice(1);
                    var phone   = c.phone ? c.phone.replace(/\D/g, '') : '';

                    var tr = document.createElement('tr');
                    tr.setAttribute('data-nombre', c.name || '');
                    tr.setAttribute('data-estado',  'prospecto');

                    // Fecha
                    var tdDate = document.createElement('td'); tdDate.className = 'col-date'; tdDate.textContent = dateStr;

                    // Nombre
                    var tdName = document.createElement('td'); tdName.className = 'col-name'; tdName.textContent = c.name || '—';

                    // Servicio (plan_type)
                    var tdSvc = document.createElement('td'); tdSvc.textContent = plan || '—';

                    // Estado
                    var tdBadge = document.createElement('td');
                    var badge = document.createElement('span'); badge.className = 'badge badge-new'; badge.textContent = 'NUEVO';
                    tdBadge.appendChild(badge);

                    // Acciones
                    var tdActions = document.createElement('td');
                    var btnVer = document.createElement('button');
                    btnVer.className = 'btn-action'; btnVer.type = 'button'; btnVer.textContent = 'Ver';
                    tdActions.appendChild(btnVer);

                    if (phone) {
                        var waAnchor = document.createElement('a');
                        waAnchor.className = 'btn-action wa';
                        waAnchor.href = 'https://wa.me/' + phone;
                        waAnchor.target = '_blank'; waAnchor.rel = 'noopener noreferrer';
                        waAnchor.textContent = 'WhatsApp';
                        tdActions.appendChild(waAnchor);
                    }

                    [tdDate, tdName, tdSvc, tdBadge, tdActions].forEach(function(td) { tr.appendChild(td); });
                    tbody.appendChild(tr);
                });

                // Actualizar también el dashTbody (solicitudes recientes)
                if (dashTbody) {
                    dashTbody.textContent = '';
                    prospectos.slice(0, 5).forEach(function(c) {
                        var dateStr = c.created_at ? c.created_at.substring(0, 10).split('-').reverse().join('/') : '—';
                        var plan    = (c.plan_type || '').replace(/_/g, ' ');
                        plan = plan.charAt(0).toUpperCase() + plan.slice(1);
                        var phone   = c.phone ? c.phone.replace(/\D/g, '') : '';

                        var tr = document.createElement('tr');
                        var tdDate = document.createElement('td'); tdDate.className = 'col-date'; tdDate.textContent = dateStr;
                        var tdName = document.createElement('td'); tdName.className = 'col-name'; tdName.textContent = c.name || '—';
                        var tdSvc  = document.createElement('td'); tdSvc.textContent = plan || '—';
                        var tdBadge = document.createElement('td');
                        var badge = document.createElement('span'); badge.className = 'badge badge-new'; badge.textContent = 'NUEVO';
                        tdBadge.appendChild(badge);
                        var tdActions = document.createElement('td');
                        var btnVer = document.createElement('button'); btnVer.className = 'btn-action'; btnVer.type = 'button'; btnVer.textContent = 'Ver';
                        tdActions.appendChild(btnVer);
                        if (phone) {
                            var wa = document.createElement('a'); wa.className = 'btn-action wa';
                            wa.href = 'https://wa.me/' + phone; wa.target = '_blank'; wa.rel = 'noopener noreferrer';
                            wa.textContent = 'WhatsApp'; tdActions.appendChild(wa);
                        }
                        [tdDate, tdName, tdSvc, tdBadge, tdActions].forEach(function(td) { tr.appendChild(td); });
                        dashTbody.appendChild(tr);
                    });
                }

                // Actualizar contadores
                var leadsCount = document.querySelector('.sidebar-item[data-section="leads"] .sidebar-item-count');
                if (leadsCount) leadsCount.textContent = prospectos.length;
            })
            .catch(function() {}); // Silently fail — mantiene demo
    }

    /* ─── DASHBOARD METRIC CARDS ──────────────────────────── */

    function loadDashboardMetrics() {
        // Leads este mes
        apiBearerFetch('/leads/list.php')
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function(data) {
                var count = 0;
                if (Array.isArray(data)) {
                    count = data.length;
                } else if (data && typeof data.total === 'number') {
                    count = data.total;
                } else if (data && Array.isArray(data.leads)) {
                    count = data.leads.length;
                }
                var el = document.getElementById('metricLeads');
                if (el) el.textContent = String(count);
            })
            .catch(function() {
                var el = document.getElementById('metricLeads');
                if (el) el.textContent = '--';
            });

        // Proyectos activos
        apiBearerFetch('/admin/projects')
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function(data) {
                var count = 0;
                if (Array.isArray(data)) {
                    count = data.filter(function(p) {
                        return p.status === 'en_progreso' || p.status === 'revision' || p.status === 'pendiente';
                    }).length;
                } else if (data && typeof data.total === 'number') {
                    count = data.total;
                }
                var el = document.getElementById('metricProjects');
                if (el) el.textContent = String(count);
            })
            .catch(function() {
                var el = document.getElementById('metricProjects');
                if (el) el.textContent = '--';
            });

        // Ingresos del mes
        apiBearerFetch('/admin/invoices')
            .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
            .then(function(data) {
                var total = 0;
                if (Array.isArray(data)) {
                    data.forEach(function(inv) {
                        if (inv.amount) total += Number(inv.amount);
                    });
                } else if (data && typeof data.total === 'number') {
                    total = data.total;
                }
                var el = document.getElementById('metricIngresos');
                if (el) {
                    if (total >= 1000000) {
                        el.textContent = '$' + (total / 1000000).toFixed(1) + 'M';
                    } else if (total > 0) {
                        el.textContent = '$' + total.toLocaleString('es-CO');
                    } else {
                        el.textContent = '--';
                    }
                }
            })
            .catch(function() {
                var el = document.getElementById('metricIngresos');
                if (el) el.textContent = '--';
            });

        // Mensajes chatbot — placeholder
        var chatEl = document.getElementById('metricChatbot');
        if (chatEl) chatEl.textContent = '--';
    }

    // Iniciar carga de datos live (no bloquea UI si API no responde)
    loadDashboardMetrics();
    loadLiveClients();
    loadLiveProjects();
    loadLiveLeads();
