<?php
declare(strict_types=1);

/**
 * ProWay Lab — Email helper
 *
 * sendEmail($to, $subject, $htmlBody)
 * sendNotification($type, $data)  — high-level wrapper
 *
 * Uses PHP mail() by default. For SMTP, set env vars:
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS
 * and install a library like PHPMailer. For now, mail() works in Docker.
 */

define('PW_MAIL_FROM',      'ProWay Lab <info@prowaylab.com>');
define('PW_ADMIN_EMAIL',    'info@prowaylab.com');
define('PW_ADMIN_CC',       'analyticfitness@gmail.com');

/**
 * Send an HTML email.
 * Returns true on success, false on failure (never throws).
 */
function sendEmail(string $to, string $subject, string $htmlBody): bool {
    $headers  = "From: " . PW_MAIL_FROM . "\r\n";
    $headers .= "Reply-To: " . PW_ADMIN_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: ProWayLab/1.0\r\n";

    try {
        return @mail($to, $subject, $htmlBody, $headers);
    } catch (\Throwable $e) {
        error_log('[ProWay Mailer] Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Build a branded ProWay email from a template.
 */
function buildEmailHtml(string $title, string $bodyContent, string $ctaText = '', string $ctaUrl = ''): string {
    $cta = '';
    if ($ctaText && $ctaUrl) {
        $cta = '<tr><td style="padding:24px 0 0 0;text-align:center">
            <a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES) . '" style="display:inline-block;padding:12px 32px;background:#00D9FF;color:#000;font-weight:700;text-decoration:none;font-family:Montserrat,Arial,sans-serif;font-size:14px;letter-spacing:0.04em">
            ' . htmlspecialchars($ctaText, ENT_QUOTES) . '</a>
        </td></tr>';
    }

    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0C0C0F;font-family:Inter,Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0C0C0F;padding:32px 0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#111114;border:1px solid #252528;max-width:560px;width:100%">

<!-- Header -->
<tr><td style="background:#191920;padding:20px 24px;border-bottom:2px solid #00D9FF">
    <span style="font-family:Montserrat,Arial,sans-serif;font-size:18px;font-weight:700;color:#fff;letter-spacing:0.04em;text-transform:uppercase">PROWAY LAB</span>
    <span style="font-family:monospace;font-size:11px;color:#A1A1AA;margin-left:12px">// ' . htmlspecialchars($title, ENT_QUOTES) . '</span>
</td></tr>

<!-- Body -->
<tr><td style="padding:28px 24px;color:#e0e0e0;font-size:14px;line-height:1.7">
' . $bodyContent . '
</td></tr>

<!-- CTA -->
' . $cta . '

<!-- Footer -->
<tr><td style="padding:20px 24px;border-top:1px solid #252528;font-size:11px;color:#A1A1AA;text-align:center;font-family:monospace">
    ProWay Lab // Ingenieria de Marcas Fitness<br>
    <a href="https://prowaylab.com" style="color:#00D9FF;text-decoration:none">prowaylab.com</a>
    &nbsp;&middot;&nbsp;
    <a href="https://wa.me/573124904720" style="color:#00D9FF;text-decoration:none">WhatsApp</a>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

/**
 * High-level notification sender.
 * $type: 'new_lead' | 'project_created' | 'project_status' | 'project_delivered' | 'invoice_sent' | 'payment_received'
 * $data: associative array with relevant fields
 */
function sendNotification(string $type, array $data): void {
    switch ($type) {

        case 'new_lead':
            $body = '<p style="margin:0 0 12px 0">Nuevo prospecto registrado:</p>
                <table cellpadding="4" cellspacing="0" style="font-size:13px;color:#e0e0e0;font-family:monospace">
                <tr><td style="color:#A1A1AA">Nombre</td><td>' . htmlspecialchars($data['nombre'] ?? '', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">Email</td><td>' . htmlspecialchars($data['email'] ?? '', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">WhatsApp</td><td>' . htmlspecialchars($data['whatsapp'] ?? '—', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">Servicio</td><td>' . htmlspecialchars($data['servicio'] ?? '—', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">Mensaje</td><td>' . htmlspecialchars($data['mensaje'] ?? '—', ENT_QUOTES) . '</td></tr>
                </table>';
            $html = buildEmailHtml('NUEVO PROSPECTO', $body, 'Ver en Admin', 'https://prowaylab.com/admin.html');
            sendEmail(PW_ADMIN_EMAIL, 'Nuevo prospecto: ' . ($data['nombre'] ?? 'Sin nombre'), $html);
            if (PW_ADMIN_CC) sendEmail(PW_ADMIN_CC, 'Nuevo prospecto: ' . ($data['nombre'] ?? 'Sin nombre'), $html);
            break;

        case 'project_status':
            $statusLabels = [
                'cotizacion' => 'En cotizacion',
                'confirmado' => 'Confirmado',
                'en_produccion' => 'En produccion',
                'revision' => 'En revision',
                'entregado' => 'Entregado',
                'facturado' => 'Facturado',
                'pagado' => 'Pagado',
            ];
            $status = $data['status'] ?? '';
            $label = $statusLabels[$status] ?? $status;
            $body = '<p style="margin:0 0 12px 0">Hola <strong>' . htmlspecialchars($data['client_name'] ?? '', ENT_QUOTES) . '</strong>,</p>
                <p style="margin:0 0 16px 0">Tu proyecto <span style="color:#00D9FF;font-family:monospace">' . htmlspecialchars($data['project_code'] ?? '', ENT_QUOTES) . '</span> ha sido actualizado:</p>
                <table cellpadding="4" cellspacing="0" style="font-size:13px;color:#e0e0e0;font-family:monospace">
                <tr><td style="color:#A1A1AA">Proyecto</td><td>' . htmlspecialchars($data['title'] ?? $data['project_code'] ?? '', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">Estado</td><td style="color:#00D9FF;font-weight:700">' . htmlspecialchars($label, ENT_QUOTES) . '</td></tr>
                </table>';
            $cta = ($status === 'entregado') ? 'Ver Entregables' : 'Ver mi Proyecto';
            $html = buildEmailHtml('PROYECTO ACTUALIZADO', $body, $cta, 'https://prowaylab.com/cliente.html');
            if (!empty($data['client_email'])) {
                sendEmail($data['client_email'], 'Proyecto ' . ($data['project_code'] ?? '') . ' — ' . $label, $html);
            }
            break;

        case 'invoice_sent':
            $body = '<p style="margin:0 0 12px 0">Hola <strong>' . htmlspecialchars($data['client_name'] ?? '', ENT_QUOTES) . '</strong>,</p>
                <p style="margin:0 0 16px 0">Tienes una nueva factura:</p>
                <table cellpadding="4" cellspacing="0" style="font-size:13px;color:#e0e0e0;font-family:monospace">
                <tr><td style="color:#A1A1AA">Factura</td><td>' . htmlspecialchars($data['invoice_number'] ?? '', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">Total</td><td style="color:#00D9FF;font-weight:700">$' . number_format((float)($data['total_cop'] ?? 0), 0, ',', '.') . ' COP</td></tr>
                <tr><td style="color:#A1A1AA">Vence</td><td>' . htmlspecialchars($data['due_date'] ?? '—', ENT_QUOTES) . '</td></tr>
                </table>';
            $html = buildEmailHtml('NUEVA FACTURA', $body, 'Ver Factura', 'https://prowaylab.com/cliente.html');
            if (!empty($data['client_email'])) {
                sendEmail($data['client_email'], 'Factura ' . ($data['invoice_number'] ?? '') . ' — $' . number_format((float)($data['total_cop'] ?? 0), 0, ',', '.') . ' COP', $html);
            }
            break;

        case 'payment_received':
            // Notify client
            $bodyClient = '<p style="margin:0 0 12px 0">Hola <strong>' . htmlspecialchars($data['client_name'] ?? '', ENT_QUOTES) . '</strong>,</p>
                <p style="margin:0 0 16px 0">Confirmamos la recepcion de tu pago:</p>
                <table cellpadding="4" cellspacing="0" style="font-size:13px;color:#e0e0e0;font-family:monospace">
                <tr><td style="color:#A1A1AA">Factura</td><td>' . htmlspecialchars($data['invoice_number'] ?? '', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">Monto</td><td style="color:#00FF87;font-weight:700">$' . number_format((float)($data['total_cop'] ?? 0), 0, ',', '.') . ' COP</td></tr>
                <tr><td style="color:#A1A1AA">Referencia</td><td>' . htmlspecialchars($data['reference'] ?? '—', ENT_QUOTES) . '</td></tr>
                </table>
                <p style="margin:16px 0 0 0;color:#A1A1AA;font-size:12px">Gracias por confiar en ProWay Lab.</p>';
            $htmlClient = buildEmailHtml('PAGO CONFIRMADO', $bodyClient, 'Ver mi Cuenta', 'https://prowaylab.com/cliente.html');
            if (!empty($data['client_email'])) {
                sendEmail($data['client_email'], 'Pago confirmado — ' . ($data['invoice_number'] ?? ''), $htmlClient);
            }
            // Notify admin
            $bodyAdmin = '<p style="margin:0 0 12px 0">Pago recibido:</p>
                <table cellpadding="4" cellspacing="0" style="font-size:13px;color:#e0e0e0;font-family:monospace">
                <tr><td style="color:#A1A1AA">Cliente</td><td>' . htmlspecialchars($data['client_name'] ?? '', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">Factura</td><td>' . htmlspecialchars($data['invoice_number'] ?? '', ENT_QUOTES) . '</td></tr>
                <tr><td style="color:#A1A1AA">Monto</td><td style="color:#00FF87;font-weight:700">$' . number_format((float)($data['total_cop'] ?? 0), 0, ',', '.') . ' COP</td></tr>
                <tr><td style="color:#A1A1AA">Metodo</td><td>' . htmlspecialchars($data['payment_method'] ?? 'PayU', ENT_QUOTES) . '</td></tr>
                </table>';
            $htmlAdmin = buildEmailHtml('PAGO RECIBIDO', $bodyAdmin, 'Ver en Admin', 'https://prowaylab.com/admin.html');
            sendEmail(PW_ADMIN_EMAIL, 'Pago recibido — ' . ($data['client_name'] ?? '') . ' — $' . number_format((float)($data['total_cop'] ?? 0), 0, ',', '.'), $htmlAdmin);
            break;
    }
}
