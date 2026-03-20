<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\Pdf;

/**
 * PdfRenderer — renders a print-ready HTML invoice page.
 *
 * Reuses the same visual design as api/partials/invoice-pdf.php but as a
 * standalone service that can be called from the V1 controller layer.
 * The output is a complete HTML document optimised for the browser's
 * built-in "Print → Save as PDF" feature.
 */
class PdfRenderer
{
    /**
     * Render a print-ready HTML page for an invoice.
     *
     * @param array $invoice  Invoice row from the database.
     * @param array $client   Client row from the database.
     * @return string         Complete HTML document.
     */
    public function renderInvoiceHtml(array $invoice, array $client): string
    {
        // ── Format helpers ──────────────────────────────────────────────
        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $money = function (mixed $v): string {
            $n = (float) ($v ?? 0);
            return $n > 0 ? '$' . number_format($n, 0, ',', '.') . ' COP' : '—';
        };

        $date = fn(string $raw): string => $raw ? date('d/m/Y', strtotime($raw)) : '—';

        // ── Extract invoice data ────────────────────────────────────────
        $invoiceId   = (int) ($invoice['id'] ?? 0);
        $number      = $h($invoice['invoice_number'] ?? 'INV-' . $invoiceId);
        $concept     = $h($invoice['notes'] ?? 'Servicios de producción de video — ProWay Lab');
        $amountCop   = $money($invoice['amount_cop'] ?? 0);
        $taxCop      = $money($invoice['tax_cop'] ?? 0);
        $totalCop    = $money($invoice['total_cop'] ?? $invoice['amount_cop'] ?? 0);
        $status      = $invoice['status'] ?? 'pendiente';
        $createdDate = $date($invoice['created_at'] ?? '');
        $dueDate     = $date($invoice['due_date'] ?? '');
        $paidDate    = !empty($invoice['paid_at']) ? $date($invoice['paid_at']) : null;

        // ── Extract client data ─────────────────────────────────────────
        $clientName    = $h($client['nombre'] ?? $client['name'] ?? 'Cliente');
        $clientEmail   = $h($client['email'] ?? '');
        $clientPhone   = $h($client['phone'] ?? '');
        $clientCompany = $h($client['company'] ?? '');

        // ── Status display ──────────────────────────────────────────────
        $statusLabel = match ($status) {
            'pagada'    => 'PAGADA',
            'pendiente' => 'PENDIENTE',
            'enviada'   => 'ENVIADA',
            'vencida'   => 'VENCIDA',
            'cancelada' => 'CANCELADA',
            default     => strtoupper($status),
        };
        $statusColor = match ($status) {
            'pagada'  => '#16a34a',
            'vencida' => '#ef4444',
            default   => '#2563eb',
        };

        // ── Tax display ─────────────────────────────────────────────────
        $taxDisplay = $taxCop !== '—' && ($invoice['tax_cop'] ?? 0) > 0 ? $taxCop : '$0 COP';
        $showTaxRow = ($invoice['tax_cop'] ?? 0) > 0;

        // ── Payment info ────────────────────────────────────────────────
        $paymentMethod = !empty($invoice['payment_method']) ? $h($invoice['payment_method']) : '';

        // ── Build HTML ──────────────────────────────────────────────────
        $paidStampHtml = '';
        if ($status === 'pagada') {
            $paidExtra  = $paidDate ? " el $paidDate" : '';
            $paidExtra .= $paymentMethod ? " · vía $paymentMethod" : '';
            $paidStampHtml = <<<HTML
            <div class="paid-stamp">&#10003; Pago recibido{$paidExtra}</div>
            HTML;
        }

        $clientCompanyHtml = $clientCompany ? "<p>{$clientCompany}</p>" : '';
        $clientEmailHtml   = $clientEmail ? "<p>{$clientEmail}</p>" : '';
        $clientPhoneHtml   = $clientPhone ? "<p>{$clientPhone}</p>" : '';

        $dueDateHtml = $dueDate !== '—'
            ? "<p>Vencimiento: <strong>{$dueDate}</strong></p>"
            : '';
        $paidDateHtml = $paidDate
            ? "<p style=\"color:#16a34a\">Pagado el: <strong>{$paidDate}</strong></p>"
            : '';

        $taxRowHtml = $showTaxRow
            ? "<tr><td>IVA</td><td>{$taxCop}</td></tr>"
            : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Factura {$number} — ProWay Lab</title>
        <style>
        /* ── Screen styles ──────────────────────────────────── */
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:"Segoe UI",system-ui,Arial,sans-serif;font-size:14px;color:#1a1a2e;background:#f0f2f8;padding:40px 20px;line-height:1.5}
        .toolbar{display:flex;align-items:center;gap:12px;margin:0 auto 24px;max-width:800px;flex-wrap:wrap}
        .print-btn{display:flex;align-items:center;gap:8px;padding:10px 22px;background:#4F8EFF;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
        .print-btn:hover{background:#3b7de8}
        .hint{color:#6b7280;font-size:13px;margin:0}
        .invoice{max-width:800px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.12);overflow:hidden}
        .inv-header{display:flex;justify-content:space-between;align-items:flex-start;padding:36px 40px 28px;border-bottom:3px solid #4F8EFF}
        .brand h1{color:#4F8EFF;font-size:26px;font-weight:800;letter-spacing:-.5px}
        .brand p{color:#6b7280;font-size:12px;margin-top:4px}
        .inv-number-block{text-align:right}
        .inv-number-block .label{font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em}
        .inv-number-block .num{font-size:22px;font-weight:700;color:#1a1a2e;margin-top:2px}
        .status-stamp{display:inline-block;border:2px solid {$statusColor};color:{$statusColor};font-size:11px;font-weight:700;padding:3px 10px;border-radius:4px;letter-spacing:.1em;margin-top:6px;transform:rotate(-2deg)}
        .meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;padding:28px 40px;border-bottom:1px solid #e5e7eb}
        .meta-block .label{font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-weight:600}
        .meta-block p{font-size:14px;color:#374151}
        .meta-block strong{font-weight:600;color:#111827}
        .line-items{padding:28px 40px}
        .line-items table{width:100%;border-collapse:collapse}
        .line-items th{font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;padding:8px 12px;border-bottom:2px solid #e5e7eb;text-align:left;font-weight:600}
        .line-items th:last-child,.line-items td:last-child{text-align:right}
        .line-items td{padding:12px 12px;border-bottom:1px solid #f3f4f6;color:#374151}
        .totals{padding:0 40px 36px}
        .totals table{width:100%;max-width:320px;margin-left:auto;border-collapse:collapse}
        .totals td{padding:6px 8px;font-size:14px}
        .totals .total-row td{border-top:2px solid #e5e7eb;font-size:16px;font-weight:700;color:#111827;padding-top:10px}
        .totals td:last-child{text-align:right}
        .paid-stamp{margin:0 40px 36px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px 16px;color:#16a34a;font-size:13px;font-weight:600}
        .footer{background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;color:#9ca3af;font-size:12px}

        /* ── Print styles ───────────────────────────────────── */
        @media print{
            body{background:#fff;padding:0}
            .toolbar,.no-print{display:none!important}
            .invoice{box-shadow:none;border-radius:0;max-width:100%}
            a{text-decoration:none;color:inherit}
            @page{margin:15mm;size:A4 portrait}
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

        <div class="invoice">
            <!-- Header -->
            <div class="inv-header">
                <div class="brand">
                    <h1>ProWay Lab</h1>
                    <p>Soluciones digitales para marcas fitness</p>
                    <p>info@prowaylab.com · prowaylab.com</p>
                </div>
                <div class="inv-number-block">
                    <div class="label">Factura</div>
                    <div class="num">{$number}</div>
                    <div class="status-stamp">{$statusLabel}</div>
                </div>
            </div>

            <!-- Meta info -->
            <div class="meta-grid">
                <div class="meta-block">
                    <div class="label">Facturado a</div>
                    <p><strong>{$clientName}</strong></p>
                    {$clientCompanyHtml}
                    {$clientEmailHtml}
                    {$clientPhoneHtml}
                </div>
                <div class="meta-block" style="text-align:right">
                    <div class="label" style="text-align:right">Fechas</div>
                    <p>Emisi&oacute;n: <strong>{$createdDate}</strong></p>
                    {$dueDateHtml}
                    {$paidDateHtml}
                </div>
            </div>

            <!-- Line items -->
            <div class="line-items">
                <table>
                    <thead>
                        <tr>
                            <th style="width:60%">Descripci&oacute;n</th>
                            <th>Subtotal</th>
                            <th>IVA</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{$concept}</td>
                            <td>{$amountCop}</td>
                            <td>{$taxDisplay}</td>
                            <td><strong>{$totalCop}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="totals">
                <table>
                    <tr><td>Subtotal</td><td>{$amountCop}</td></tr>
                    {$taxRowHtml}
                    <tr class="total-row"><td>Total a pagar</td><td>{$totalCop}</td></tr>
                </table>
            </div>

            {$paidStampHtml}

            <!-- Footer -->
            <div class="footer">
                <p>ProWay Lab · NIT: (por definir) · info@prowaylab.com · prowaylab.com</p>
                <p style="margin-top:4px">Este documento fue generado autom&aacute;ticamente y no requiere firma.</p>
            </div>
        </div>

        </body>
        </html>
        HTML;
    }
}
