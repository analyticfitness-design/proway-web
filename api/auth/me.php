<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');

$tokenRow = verifyToken(getBearerToken());
if (!$tokenRow) {
    respondError('Unauthorized', 401);
}

$db = getDB();

// ── ADMIN ────────────────────────────────────────────────────────────────────
if ($tokenRow['user_type'] === 'admin') {
    $stmt = $db->prepare('SELECT id, username, name, role, created_at FROM admins WHERE id = ?');
    $stmt->execute([$tokenRow['user_id']]);
    $admin = $stmt->fetch();

    if (!$admin) respondError('Unauthorized', 401);

    respond([
        'user_type' => 'admin',
        'user'      => $admin,
    ]);
}

// ── CLIENT ───────────────────────────────────────────────────────────────────
$stmt = $db->prepare(
    'SELECT c.id, c.code, c.name, c.email, c.phone, c.company, c.instagram,
            c.plan_type, c.status, c.notes, c.created_at,
            cp.brand_name, cp.brand_colors, cp.target_audience,
            cp.content_style, cp.platforms, cp.monthly_video_goal
     FROM clients c
     LEFT JOIN client_profiles cp ON cp.client_id = c.id
     WHERE c.id = ?'
);
$stmt->execute([$tokenRow['user_id']]);
$client = $stmt->fetch();

if (!$client) respondError('Unauthorized', 401);

// Decode JSON fields
foreach (['brand_colors', 'platforms'] as $field) {
    if (isset($client[$field]) && is_string($client[$field])) {
        $client[$field] = json_decode($client[$field], true);
    }
}

// Active project count
$pjStmt = $db->prepare(
    "SELECT COUNT(*) as cnt FROM projects
     WHERE client_id = ? AND status NOT IN ('entregado','facturado','pagado')"
);
$pjStmt->execute([$client['id']]);
$client['active_projects'] = (int) $pjStmt->fetch()['cnt'];

respond([
    'user_type' => 'client',
    'user'      => $client,
]);
