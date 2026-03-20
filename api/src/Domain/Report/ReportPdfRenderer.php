<?php
declare(strict_types=1);

namespace ProWay\Domain\Report;

/**
 * ReportPdfRenderer -- renders a print-ready HTML monthly report.
 *
 * Follows the same pattern as Infrastructure\Pdf\PdfRenderer (invoice):
 * outputs a complete HTML document optimised for "Print -> Save as PDF".
 * Branded with ProWay dark theme, cyan accents, Montserrat headings.
 */
class ReportPdfRenderer
{
    /**
     * Render a monthly progress report as a complete HTML document.
     */
    public function render(array $reportData): string
    {
        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        // ── Extract data ────────────────────────────────────────────────────
        $client       = $reportData['client'] ?? [];
        $clientName   = $h($client['nombre'] ?? $client['name'] ?? 'Cliente');
        $clientEmail  = $h($client['email'] ?? '');
        $periodLabel  = $h($reportData['period_label'] ?? '');
        $year         = (int) ($reportData['year'] ?? date('Y'));
        $month        = (int) ($reportData['month'] ?? date('n'));
        $projects     = $reportData['projects'] ?? [];
        $deliverables = $reportData['deliverables'] ?? [];
        $social       = $reportData['social'] ?? [];
        $recommendations = $h($reportData['recommendations'] ?? '');

        // ── Status labels ───────────────────────────────────────────────────
        $statusLabels = [
            'cotizacion'     => ['Cotizaci&oacute;n', '#9ca3af'],
            'confirmado'     => ['Confirmado',        '#3b82f6'],
            'en_produccion'  => ['En producci&oacute;n', '#f59e0b'],
            'revision'       => ['Revisi&oacute;n',   '#a855f7'],
            'entregado'      => ['Entregado',         '#22c55e'],
            'facturado'      => ['Facturado',         '#06b6d4'],
            'pagado'         => ['Pagado',            '#16a34a'],
        ];

        // ── Month names ─────────────────────────────────────────────────────
        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        // ── Build projects table ────────────────────────────────────────────
        $projectRows = '';
        if (empty($projects)) {
            $projectRows = '<tr><td colspan="4" style="text-align:center;color:#6b7280;">Sin proyectos registrados</td></tr>';
        } else {
            foreach ($projects as $p) {
                $pName   = $h($p['service_type'] ?? $p['title'] ?? 'Proyecto');
                $pStatus = $p['status'] ?? 'cotizacion';
                [$sLabel, $sColor] = $statusLabels[$pStatus] ?? [ucfirst($pStatus), '#6b7280'];
                $pPrice  = isset($p['price_cop']) && (float) $p['price_cop'] > 0
                    ? '$' . number_format((float) $p['price_cop'], 0, ',', '.') . ' COP'
                    : '&mdash;';
                $pDate = !empty($p['created_at'])
                    ? date('d/m/Y', strtotime($p['created_at']))
                    : '&mdash;';

                $projectRows .= <<<HTML
                <tr>
                    <td>{$pName}</td>
                    <td><span class="status-badge" style="background:{$sColor}20;color:{$sColor};border:1px solid {$sColor}40;">{$sLabel}</span></td>
                    <td>{$pPrice}</td>
                    <td>{$pDate}</td>
                </tr>
                HTML;
            }
        }

        // ── Build deliverables list ─────────────────────────────────────────
        $deliverableRows = '';
        if (empty($deliverables)) {
            $deliverableRows = '<tr><td colspan="4" style="text-align:center;color:#6b7280;">Sin entregables este mes</td></tr>';
        } else {
            foreach ($deliverables as $d) {
                $dTitle   = $h($d['title'] ?? '');
                $dType    = $h(ucfirst($d['type'] ?? 'archivo'));
                $dProject = $h($d['project_service'] ?? '');
                $dDate    = !empty($d['delivered_at'])
                    ? date('d/m/Y', strtotime($d['delivered_at']))
                    : '&mdash;';

                $deliverableRows .= <<<HTML
                <tr>
                    <td>{$dTitle}</td>
                    <td>{$dType}</td>
                    <td>{$dProject}</td>
                    <td>{$dDate}</td>
                </tr>
                HTML;
            }
        }

        // ── Build social section ────────────────────────────────────────────
        $socialHtml = '';
        if (!empty($social)) {
            $socialHtml = '<div class="section"><h2 class="section-title">Redes Sociales</h2>';

            foreach ($social as $s) {
                $platform = $h(ucfirst($s['platform'] ?? ''));
                $username = $h('@' . ($s['username'] ?? ''));
                $fStart   = number_format($s['followers_start'] ?? 0);
                $fEnd     = number_format($s['followers_end'] ?? 0);
                $fGrowth  = (int) ($s['follower_growth'] ?? 0);
                $growthSign  = $fGrowth >= 0 ? '+' : '';
                $growthColor = $fGrowth >= 0 ? '#22c55e' : '#ef4444';
                $likes    = number_format($s['total_likes'] ?? 0);
                $comments = number_format($s['total_comments'] ?? 0);
                $views    = number_format($s['total_views'] ?? 0);
                $engagement = number_format($s['avg_engagement'] ?? 0, 2) . '%';

                $socialHtml .= <<<HTML
                <div class="social-card">
                    <div class="social-header">
                        <strong>{$platform}</strong> &middot; {$username}
                    </div>
                    <div class="metrics-grid">
                        <div class="metric-box">
                            <div class="metric-label">Seguidores</div>
                            <div class="metric-value">{$fEnd}</div>
                            <div class="metric-change" style="color:{$growthColor}">{$growthSign}{$fGrowth}</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-label">Likes</div>
                            <div class="metric-value">{$likes}</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-label">Comentarios</div>
                            <div class="metric-value">{$comments}</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-label">Vistas</div>
                            <div class="metric-value">{$views}</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-label">Engagement</div>
                            <div class="metric-value">{$engagement}</div>
                        </div>
                    </div>
                HTML;

                // Top posts
                if (!empty($s['top_posts'])) {
                    $socialHtml .= '<div class="top-posts"><h4>Top publicaciones del mes</h4><ol>';
                    foreach ($s['top_posts'] as $post) {
                        $caption = $h(mb_substr($post['caption'] ?? '', 0, 80));
                        if (mb_strlen($post['caption'] ?? '') > 80) {
                            $caption .= '...';
                        }
                        $pLikes    = number_format((int) ($post['likes'] ?? 0));
                        $pComments = number_format((int) ($post['comments'] ?? 0));
                        $pViews    = number_format((int) ($post['views'] ?? 0));

                        $socialHtml .= <<<HTML
                        <li>
                            <span class="post-caption">{$caption}</span>
                            <span class="post-stats">{$pLikes} likes &middot; {$pComments} comentarios &middot; {$pViews} vistas</span>
                        </li>
                        HTML;
                    }
                    $socialHtml .= '</ol></div>';
                }

                $socialHtml .= '</div>'; // close social-card
            }

            $socialHtml .= '</div>'; // close section
        }

        // ── Recommendations section ─────────────────────────────────────────
        $recsHtml = '';
        if ($recommendations !== '') {
            $recsFormatted = nl2br($recommendations);
            $recsHtml = <<<HTML
            <div class="section">
                <h2 class="section-title">Recomendaciones</h2>
                <div class="recs-box">{$recsFormatted}</div>
            </div>
            HTML;
        }

        // ── Assemble HTML ───────────────────────────────────────────────────
        $generatedDate = date('d/m/Y H:i');
        $monthName = $monthNames[$month] ?? '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reporte Mensual — {$clientName} — {$periodLabel}</title>
        <style>
        /* ── Base ─────────────────────────────────────────────── */
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Inter:wght@400;500;600&display=swap');
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',system-ui,sans-serif;font-size:13px;color:#e2e8f0;background:#0f172a;padding:40px 20px;line-height:1.6}

        /* ── Toolbar (no-print) ───────────────────────────────── */
        .toolbar{display:flex;align-items:center;gap:12px;margin:0 auto 24px;max-width:850px;flex-wrap:wrap}
        .print-btn{display:flex;align-items:center;gap:8px;padding:10px 22px;background:#06b6d4;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif}
        .print-btn:hover{background:#0891b2}
        .hint{color:#94a3b8;font-size:13px;margin:0}

        /* ── Report container ─────────────────────────────────── */
        .report{max-width:850px;margin:0 auto;background:#1e293b;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.4);overflow:hidden}

        /* ── Header ───────────────────────────────────────────── */
        .report-header{background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%);padding:40px;border-bottom:3px solid #06b6d4;position:relative;overflow:hidden}
        .report-header::before{content:'';position:absolute;top:-50%;right:-30%;width:400px;height:400px;background:radial-gradient(circle,rgba(6,182,212,.12) 0%,transparent 60%);pointer-events:none}
        .brand{display:flex;justify-content:space-between;align-items:flex-start}
        .brand-left h1{font-family:'Montserrat',sans-serif;font-size:28px;font-weight:800;color:#06b6d4;letter-spacing:-.5px}
        .brand-left p{color:#94a3b8;font-size:12px;margin-top:2px}
        .report-meta{text-align:right}
        .report-meta .period{font-family:'Montserrat',sans-serif;font-size:22px;font-weight:700;color:#f1f5f9}
        .report-meta .client-name{font-size:14px;color:#06b6d4;margin-top:4px;font-weight:600}
        .report-meta .gen-date{font-size:11px;color:#64748b;margin-top:4px}

        /* ── Sections ─────────────────────────────────────────── */
        .section{padding:32px 40px;border-bottom:1px solid #334155}
        .section:last-child{border-bottom:none}
        .section-title{font-family:'Montserrat',sans-serif;font-size:16px;font-weight:700;color:#06b6d4;margin-bottom:16px;text-transform:uppercase;letter-spacing:.08em}

        /* ── Tables ───────────────────────────────────────────── */
        table{width:100%;border-collapse:collapse}
        th{font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;padding:10px 12px;border-bottom:2px solid #334155;text-align:left;font-weight:600}
        td{padding:10px 12px;border-bottom:1px solid #1e293b;color:#cbd5e1;font-size:13px}
        tr:hover{background:rgba(6,182,212,.04)}

        /* ── Status badges ────────────────────────────────────── */
        .status-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;letter-spacing:.04em}

        /* ── Social cards ─────────────────────────────────────── */
        .social-card{background:#0f172a;border:1px solid #334155;border-radius:12px;padding:20px;margin-bottom:16px}
        .social-header{font-size:15px;color:#f1f5f9;margin-bottom:12px}
        .metrics-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
        .metric-box{background:#1e293b;border-radius:8px;padding:12px;text-align:center}
        .metric-label{font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
        .metric-value{font-family:'Montserrat',sans-serif;font-size:18px;font-weight:700;color:#f1f5f9}
        .metric-change{font-size:12px;font-weight:600;margin-top:2px}
        .top-posts{margin-top:16px;padding-top:16px;border-top:1px solid #334155}
        .top-posts h4{font-size:12px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
        .top-posts ol{padding-left:20px}
        .top-posts li{margin-bottom:8px}
        .post-caption{display:block;color:#cbd5e1;font-size:12px}
        .post-stats{display:block;color:#64748b;font-size:11px;margin-top:2px}

        /* ── Recommendations ──────────────────────────────────── */
        .recs-box{background:#0f172a;border:1px solid #334155;border-left:4px solid #06b6d4;border-radius:8px;padding:20px;color:#cbd5e1;font-size:13px;line-height:1.7}

        /* ── Footer ───────────────────────────────────────────── */
        .report-footer{background:#0f172a;border-top:1px solid #334155;padding:20px 40px;text-align:center;color:#64748b;font-size:12px}
        .report-footer a{color:#06b6d4;text-decoration:none}

        /* ── Print styles ─────────────────────────────────────── */
        @media print{
            body{background:#1e293b;padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .toolbar,.no-print{display:none!important}
            .report{box-shadow:none;border-radius:0;max-width:100%}
            .section{page-break-inside:avoid}
            a{text-decoration:none;color:inherit}
            @page{margin:10mm;size:A4 portrait}
        }
        </style>
        </head>
        <body>

        <div class="toolbar no-print">
            <button class="print-btn" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Imprimir / Guardar como PDF
            </button>
            <p class="hint">Usa <strong>Ctrl+P</strong> y selecciona "Guardar como PDF" en el destino.</p>
        </div>

        <div class="report">
            <!-- Header -->
            <div class="report-header">
                <div class="brand">
                    <div class="brand-left">
                        <h1>ProWay Lab</h1>
                        <p>Soluciones digitales para marcas fitness</p>
                    </div>
                    <div class="report-meta">
                        <div class="period">{$monthName} {$year}</div>
                        <div class="client-name">{$clientName}</div>
                        <div class="gen-date">Generado: {$generatedDate}</div>
                    </div>
                </div>
            </div>

            <!-- Project Status -->
            <div class="section">
                <h2 class="section-title">Estado de Proyectos</h2>
                <table>
                    <thead>
                        <tr>
                            <th style="width:35%">Servicio</th>
                            <th>Estado</th>
                            <th>Valor</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$projectRows}
                    </tbody>
                </table>
            </div>

            <!-- Deliverables -->
            <div class="section">
                <h2 class="section-title">Entregables del Mes</h2>
                <table>
                    <thead>
                        <tr>
                            <th style="width:30%">T&iacute;tulo</th>
                            <th>Tipo</th>
                            <th>Proyecto</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$deliverableRows}
                    </tbody>
                </table>
            </div>

            <!-- Social Media -->
            {$socialHtml}

            <!-- Recommendations -->
            {$recsHtml}

            <!-- Footer -->
            <div class="report-footer">
                <p>ProWay Lab &middot; info@prowaylab.com &middot; <a href="https://prowaylab.com">prowaylab.com</a></p>
                <p style="margin-top:4px">Reporte de avance mensual &mdash; {$periodLabel}</p>
                <p style="margin-top:4px">Este documento fue generado autom&aacute;ticamente.</p>
            </div>
        </div>

        </body>
        </html>
        HTML;
    }
}
