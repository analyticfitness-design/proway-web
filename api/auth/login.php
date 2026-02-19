<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('POST');

$body     = getJsonBody();
$userType = trim($body['type'] ?? 'client');  // 'admin' | 'client'
$email    = trim($body['email'] ?? $body['username'] ?? '');
$password = $body['password'] ?? '';

if (!$email || !$password) {
    respondError('Se requieren credenciales', 422);
}

$db = getDB();

// ── ADMIN LOGIN ──────────────────────────────────────────────────────────────
if ($userType === 'admin') {
    $stmt = $db->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        respondError('Credenciales inválidas', 401);
    }

    $token = createToken('admin', (int) $admin['id']);

    respond([
        'token'      => $token,
        'expires_in' => TOKEN_EXPIRY_ADMIN * 3600,
        'user_type'  => 'admin',
        'user'       => [
            'id'       => $admin['id'],
            'username' => $admin['username'],
            'name'     => $admin['name'],
            'role'     => $admin['role'],
        ],
    ]);
}

// ── CLIENT LOGIN ─────────────────────────────────────────────────────────────
$stmt = $db->prepare('SELECT * FROM clients WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$client = $stmt->fetch();

if (!$client) {
    respondError('Credenciales inválidas', 401);
}

// Password stored in client_profiles.password_hash
$profStmt = $db->prepare('SELECT password_hash FROM client_profiles WHERE client_id = ? LIMIT 1');
$profStmt->execute([$client['id']]);
$profile = $profStmt->fetch();

if (!$profile || !password_verify($password, $profile['password_hash'])) {
    respondError('Credenciales inválidas', 401);
}

if ($client['status'] === 'inactivo') {
    respondError('Cuenta inactiva. Contacta a ProWay Lab.', 403);
}

// Count active projects
$pjStmt = $db->prepare(
    "SELECT COUNT(*) as cnt FROM projects
     WHERE client_id = ? AND status NOT IN ('entregado','facturado','pagado')"
);
$pjStmt->execute([$client['id']]);
$activeProjects = (int) $pjStmt->fetch()['cnt'];

$token = createToken('client', (int) $client['id']);

respond([
    'token'      => $token,
    'expires_in' => TOKEN_EXPIRY_CLIENT * 3600,
    'user_type'  => 'client',
    'user'       => [
        'id'             => $client['id'],
        'code'           => $client['code'],
        'name'           => $client['name'],
        'email'          => $client['email'],
        'company'        => $client['company'],
        'plan_type'      => $client['plan_type'],
        'status'         => $client['status'],
        'active_projects'=> $activeProjects,
    ],
]);
