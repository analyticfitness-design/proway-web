<?php
/**
 * POST /api/auth/forgot-password.php
 * Body: { "email": "user@example.com" }
 * Rate limit: 3/hour per IP
 */

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['error' => 'Method not allowed']);
}

checkRateLimit('forgot_password', 3, 3600);

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

// Always respond success to avoid leaking valid emails
$successMsg = ['message' => 'Si el email existe, recibirás instrucciones.'];

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(200, $successMsg);
}

try {
    $db = getDB();

    // Look up client by email
    $stmt = $db->prepare("SELECT id, name, email FROM clients WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        // Don't reveal that email doesn't exist
        sendResponse(200, $successMsg);
    }

    // Invalidate previous tokens for this email
    $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
    $stmt->execute([$email]);

    // Generate new token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $token, $expiresAt]);

    // Build reset URL
    $resetUrl = 'https://prowaylab.com/reset-password.html?token=' . $token;

    // Send email
    $emailBody = '
        <p>Hola ' . htmlspecialchars($client['name']) . ',</p>
        <p>Recibimos una solicitud para restablecer tu contraseña en ProWay Lab.</p>
        <p>Este enlace expira en <strong>1 hora</strong>. Si no solicitaste este cambio, puedes ignorar este correo.</p>
    ';

    $htmlContent = buildEmailHtml(
        'Restablecer Contraseña',
        $emailBody,
        'Restablecer Contraseña',
        $resetUrl
    );

    sendEmail($client['email'], 'Restablecer contraseña - ProWay Lab', $htmlContent);

    sendResponse(200, $successMsg);

} catch (Exception $e) {
    // Still respond success to avoid leaking info on errors
    error_log('forgot-password error: ' . $e->getMessage());
    sendResponse(200, $successMsg);
}
