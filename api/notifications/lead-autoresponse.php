<?php
declare(strict_types=1);

/**
 * POST /api/notifications/lead-autoresponse
 *
 * Called by n8n workflow after a new lead is created.
 * Sends a WhatsApp auto-response to the lead via Meta WhatsApp Business API.
 *
 * Body JSON:
 *   lead_id    int     (required) — Client/lead ID
 *   nombre     string  (optional) — Lead name override
 *   whatsapp   string  (optional) — Phone number override
 *
 * Also accepts n8n webhook format where lead data comes from the workflow.
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('POST');

// Simple API key auth for n8n (not full admin session)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$validKey = env('N8N_API_KEY', 'proway-n8n-2026');

if ($apiKey !== $validKey) {
    respondError('API key invalida', 401);
}

$body = getJsonBody();

$leadId  = (int) ($body['lead_id'] ?? 0);
$nombre  = trim($body['nombre'] ?? '');
$wap     = trim($body['whatsapp'] ?? '');

// If lead_id given, fetch from DB
if ($leadId > 0 && (!$nombre || !$wap)) {
    $db   = getDB();
    $stmt = $db->prepare('SELECT name, phone FROM clients WHERE id = ?');
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();

    if ($lead) {
        if (!$nombre) $nombre = $lead['name'];
        if (!$wap)    $wap    = $lead['phone'];
    }
}

if (!$wap) {
    respondError('No hay numero de WhatsApp para este lead', 422);
}

// Format phone: ensure country code, remove spaces/dashes
$wap = preg_replace('/[^0-9]/', '', $wap);
if (strlen($wap) === 10) $wap = '57' . $wap; // Colombia default

// WhatsApp Business API config
$waPhoneId = env('WA_PHONE_NUMBER_ID', '');
$waToken   = env('WA_ACCESS_TOKEN', '');

if (!$waPhoneId || !$waToken) {
    // WhatsApp API not configured yet — log and return success
    respond([
        'sent'    => false,
        'reason'  => 'WhatsApp API no configurada aun',
        'lead_id' => $leadId,
    ]);
}

// Send WhatsApp message via Meta Graph API
$message = "Hola $nombre! Gracias por contactar a ProWay Lab. "
         . "Recibimos tu solicitud y un miembro de nuestro equipo te contactara pronto. "
         . "Mientras tanto, puedes ver nuestro portafolio en https://www.prowaylab.com/portafolio.html";

$payload = [
    'messaging_product' => 'whatsapp',
    'to'                => $wap,
    'type'              => 'text',
    'text'              => ['body' => $message],
];

$ch = curl_init("https://graph.facebook.com/v21.0/$waPhoneId/messages");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: Bearer $waToken",
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);

$waResult = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    respond([
        'sent'     => true,
        'lead_id'  => $leadId,
        'to'       => $wap,
        'response' => json_decode($waResult, true),
    ]);
} else {
    respond([
        'sent'      => false,
        'lead_id'   => $leadId,
        'to'        => $wap,
        'http_code' => $httpCode,
        'error'     => $curlErr ?: $waResult,
    ]);
}
