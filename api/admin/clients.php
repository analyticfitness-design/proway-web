<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$method = requireMethods(['GET', 'POST', 'PUT']);
authenticateAdmin();
$db = getDB();

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->query(
        "SELECT c.*,
                (SELECT COUNT(*) FROM projects p
                 WHERE p.client_id = c.id
                   AND p.status NOT IN ('entregado','facturado','pagado')) AS active_projects,
                (SELECT COUNT(*) FROM projects p
                 WHERE p.client_id = c.id) AS total_projects,
                CASE c.plan_type
                    WHEN 'authority' THEN 2200000
                    WHEN 'growth'    THEN 1600000
                    WHEN 'starter'   THEN 1200000
                    ELSE 0
                END AS mrr_cop
         FROM clients c
         ORDER BY c.created_at DESC"
    );
    respond($stmt->fetchAll());
}

// ── POST: crear cliente ───────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = getJsonBody();
    $required = ['name', 'email'];
    foreach ($required as $r) {
        if (empty($body[$r])) respondError("Campo requerido: $r", 422);
    }

    // Validate email uniqueness
    $chk = $db->prepare('SELECT id FROM clients WHERE email = ?');
    $chk->execute([$body['email']]);
    if ($chk->fetch()) respondError('El email ya está registrado', 409);

    // Auto-generate code pw-NNN
    $cStmt = $db->query('SELECT COUNT(*) FROM clients');
    $num   = (int) $cStmt->fetchColumn() + 1;
    $code  = sprintf('pw-%03d', $num);

    // Ensure uniqueness
    while (true) {
        $dup = $db->prepare('SELECT id FROM clients WHERE code = ?');
        $dup->execute([$code]);
        if (!$dup->fetch()) break;
        $num++;
        $code = sprintf('pw-%03d', $num);
    }

    $stmt = $db->prepare(
        'INSERT INTO clients (code, name, email, phone, company, instagram, plan_type, status, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $code,
        $body['name'],
        $body['email'],
        $body['phone']     ?? null,
        $body['company']   ?? null,
        $body['instagram'] ?? null,
        $body['plan_type'] ?? 'video_individual',
        $body['status']    ?? 'prospecto',
        $body['notes']     ?? null,
    ]);

    $clientId = (int) $db->lastInsertId();

    // Create profile with hashed temp password
    $tempPassword = $body['password'] ?? 'pw2026';
    $hash         = password_hash($tempPassword, PASSWORD_BCRYPT);

    $profStmt = $db->prepare(
        'INSERT INTO client_profiles (client_id, brand_name, monthly_video_goal, password_hash)
         VALUES (?, ?, ?, ?)'
    );
    $profStmt->execute([
        $clientId,
        $body['brand_name'] ?? $body['company'] ?? $body['name'],
        $body['monthly_video_goal'] ?? 4,
        $hash,
    ]);

    $newStmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
    $newStmt->execute([$clientId]);
    respond($newStmt->fetch(), 201);
}

// ── PUT: actualizar cliente ───────────────────────────────────────────────────
if ($method === 'PUT') {
    if (empty($_GET['id'])) respondError('Se requiere ?id=', 422);
    $clientId = (int) $_GET['id'];

    $body    = getJsonBody();
    $allowed = ['name', 'phone', 'company', 'instagram', 'plan_type', 'status', 'notes'];
    $update  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $update[$field] = $body[$field];
        }
    }

    if (empty($update)) respondError('No hay campos para actualizar', 422);

    $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update)));
    $values = array_values($update);
    $values[] = $clientId;

    $db->prepare("UPDATE clients SET $set WHERE id = ?")->execute($values);

    $updStmt = $db->prepare('SELECT * FROM clients WHERE id = ?');
    $updStmt->execute([$clientId]);
    $client = $updStmt->fetch();
    if (!$client) respondError('Cliente no encontrado', 404);

    respond($client);
}
