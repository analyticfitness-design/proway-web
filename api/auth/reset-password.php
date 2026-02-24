<?php
/**
 * POST /api/auth/reset-password.php
 * Body: { "token": "...", "password": "..." }
 * Rate limit: 5/hour per IP
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['error' => 'Method not allowed']);
}

checkRateLimit('reset_password', 5, 3600);

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

// Validate inputs
if (!$token) {
    sendResponse(400, ['error' => 'Token requerido.']);
}

if (strlen($password) < 8) {
    sendResponse(400, ['error' => 'La contraseña debe tener al menos 8 caracteres.']);
}

try {
    $db = getDB();

    // Find valid token
    $stmt = $db->prepare("
        SELECT id, email FROM password_resets
        WHERE token = ? AND used = 0 AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        sendResponse(400, ['error' => 'Token invalido o expirado.']);
    }

    // Find client by email
    $stmt = $db->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$reset['email']]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        sendResponse(400, ['error' => 'Token invalido o expirado.']);
    }

    // Update password
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare("UPDATE client_profiles SET password_hash = ? WHERE client_id = ?");
    $stmt->execute([$hash, $client['id']]);

    // Mark token as used
    $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
    $stmt->execute([$reset['id']]);

    sendResponse(200, ['message' => 'Contraseña actualizada.']);

} catch (Exception $e) {
    error_log('reset-password error: ' . $e->getMessage());
    sendResponse(500, ['error' => 'Error interno. Intenta de nuevo.']);
}
