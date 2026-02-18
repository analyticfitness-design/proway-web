<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$method   = requireMethods(['GET', 'POST', 'DELETE']);
$tokenRow = verifyToken(getBearerToken());
if (!$tokenRow) respondError('Unauthorized', 401);

$isAdmin = $tokenRow['user_type'] === 'admin';
$db      = getDB();

if ($isAdmin) {
    authenticateAdmin();
    // Admin can view any client's assets via ?client_id=
    $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
} else {
    $currentClient = authenticateClient();
    $clientId      = $currentClient['id'];
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (!$clientId) respondError('Se requiere client_id', 422);

    $stmt = $db->prepare(
        'SELECT id, client_id, asset_type, name, file_url, description, version, created_at
         FROM brand_assets WHERE client_id = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$clientId]);
    respond($stmt->fetchAll());
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = getJsonBody();

    // Clients upload their own assets; admins specify client_id
    $targetClientId = $isAdmin
        ? (int) ($body['client_id'] ?? ($clientId ?? 0))
        : $clientId;

    if (!$targetClientId) respondError('Se requiere client_id', 422);
    if (empty($body['asset_type']) || empty($body['name'])) {
        respondError('Campos requeridos: asset_type, name', 422);
    }

    $stmt = $db->prepare(
        'INSERT INTO brand_assets (client_id, asset_type, name, file_url, description, version)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
              $targetClientId,
              $body['asset_type'],
              $body['name'],
              $body['file_url']    ?? null,
              $body['description'] ?? null,
        (int) ($body['version']   ?? 1),
    ]);

    $newId   = (int) $db->lastInsertId();
    $newStmt = $db->prepare('SELECT * FROM brand_assets WHERE id = ?');
    $newStmt->execute([$newId]);

    respond($newStmt->fetch(), 201);
}

// ── DELETE (admin only) ───────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$isAdmin) respondError('Forbidden', 403);
    if (empty($_GET['id'])) respondError('Se requiere ?id=', 422);

    $db->prepare('DELETE FROM brand_assets WHERE id = ?')->execute([(int) $_GET['id']]);
    respond(['message' => 'Asset eliminado']);
}
