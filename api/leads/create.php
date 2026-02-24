<?php
declare(strict_types=1);

/**
 * POST /api/leads/create
 *
 * Endpoint público — no requiere autenticación.
 * Guarda un lead del formulario de contacto como cliente con status 'prospecto'.
 *
 * Body JSON esperado:
 *   nombre    string   (requerido)
 *   email     string   (requerido)
 *   whatsapp  string   (opcional)
 *   servicio  string   (opcional)  → plan_type
 *   mensaje   string   (opcional)  → notes
 *   instagram string   (opcional)
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('POST');

// Rate limit: 5 leads per IP per hour
if (!checkRateLimit('leads_create', 5, 3600)) {
    respondError('Demasiadas solicitudes. Intenta de nuevo en una hora.', 429);
}

$body    = getJsonBody();

// Honeypot: bots fill hidden fields. If "website" has a value, it's a bot.
$honeypot = trim($body['website'] ?? '');
if ($honeypot !== '') {
    // Silently accept to not reveal detection
    respond(['message' => 'Lead registrado', 'id' => 0, 'code' => 'ok'], 201);
}

$nombre  = trim($body['nombre']    ?? $body['name']    ?? '');
$email   = trim($body['email']     ?? '');
$wap     = trim($body['whatsapp']  ?? $body['phone']   ?? '');
$svc     = trim($body['servicio']  ?? $body['service'] ?? '');
$msg     = trim($body['mensaje']   ?? $body['message'] ?? '');
$insta   = trim($body['instagram'] ?? '');

if (!$nombre || !$email) {
    respondError('Se requieren nombre y email', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondError('Email no válido', 422);
}

// Reject disposable/obviously fake domains
$emailDomain = strtolower(substr($email, strrpos($email, '@') + 1));
$blocked = ['tempmail.com','throwaway.email','mailinator.com','guerrillamail.com','yopmail.com'];
if (in_array($emailDomain, $blocked, true)) {
    respondError('Email no válido', 422);
}

$db = getDB();

// Si ya existe un lead/cliente con ese email, evitar duplicados
$dup = $db->prepare('SELECT id, status FROM clients WHERE email = ? LIMIT 1');
$dup->execute([$email]);
$existing = $dup->fetch();

if ($existing) {
    // Ya existe — responder OK sin crear duplicado
    respond(['message' => 'Lead ya registrado', 'id' => $existing['id']]);
}

// Auto-generar código único pw-NNN
$cStmt = $db->query('SELECT COUNT(*) FROM clients');
$num   = (int) $cStmt->fetchColumn() + 1;
$code  = sprintf('pw-%03d', $num);

while (true) {
    $chk = $db->prepare('SELECT id FROM clients WHERE code = ?');
    $chk->execute([$code]);
    if (!$chk->fetch()) break;
    $num++;
    $code = sprintf('pw-%03d', $num);
}

// Notas combinadas
$notes = '';
if ($svc)  $notes .= 'Servicio: ' . $svc . "\n";
if ($msg)  $notes .= 'Mensaje: '  . $msg . "\n";
if ($wap)  $notes .= 'WhatsApp: ' . $wap;
$notes = trim($notes) ?: null;

// Mapear servicio a plan_type (aproximado)
$planMap = [
    'starter'   => 'starter',   'growth'     => 'growth',
    'authority' => 'authority', 'video'       => 'video_individual',
    'paquete'   => 'growth',    'produccion'  => 'starter',
];
$planType = 'video_individual'; // default
foreach ($planMap as $keyword => $plan) {
    if (stripos($svc, $keyword) !== false) {
        $planType = $plan;
        break;
    }
}

$stmt = $db->prepare(
    'INSERT INTO clients (code, name, email, phone, company, instagram, plan_type, status, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $code,
    $nombre,
    $email,
    $wap  ?: null,
    null,           // company — no se recoge en el form de contacto
    $insta ?: null,
    $planType,
    'prospecto',
    $notes,
]);

$newId = (int) $db->lastInsertId();

// Perfil mínimo (sin contraseña — es un lead, no tiene acceso aún)
try {
    $profStmt = $db->prepare(
        'INSERT IGNORE INTO client_profiles (client_id) VALUES (?)'
    );
    $profStmt->execute([$newId]);
} catch (\Exception $e) {
    // Ignorar si la tabla requiere campos adicionales — el lead se guardó igualmente
}

// Notify admin about new lead
sendNotification('new_lead', [
    'nombre'   => $nombre,
    'email'    => $email,
    'whatsapp' => $wap,
    'servicio' => $svc,
    'mensaje'  => $msg,
]);

respond(['message' => 'Lead registrado', 'id' => $newId, 'code' => $code], 201);
