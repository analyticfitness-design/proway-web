<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$method   = requireMethods(['GET', 'POST']);
$tokenRow = verifyToken(getBearerToken());
if (!$tokenRow) respondError('Unauthorized', 401);

$isAdmin = $tokenRow['user_type'] === 'admin';
$db      = getDB();

if ($isAdmin) {
    authenticateAdmin();
} else {
    $currentClient = authenticateClient();
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (empty($_GET['project_id'])) {
        respondError('Se requiere project_id', 422);
    }
    $projectId = (int) $_GET['project_id'];

    // Verify access
    if (!$isAdmin) {
        $check = $db->prepare('SELECT id FROM projects WHERE id = ? AND client_id = ?');
        $check->execute([$projectId, $currentClient['id']]);
        if (!$check->fetch()) respondError('Proyecto no encontrado', 404);
    }

    $stmt = $db->prepare(
        'SELECT * FROM deliverables WHERE project_id = ? ORDER BY delivered_at DESC'
    );
    $stmt->execute([$projectId]);
    respond($stmt->fetchAll());
}

// ── POST (admin only) ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    if (!$isAdmin) respondError('Forbidden', 403);

    $body = getJsonBody();
    if (empty($body['project_id']) || empty($body['title'])) {
        respondError('Campos requeridos: project_id, title', 422);
    }

    $stmt = $db->prepare(
        'INSERT INTO deliverables
         (project_id, type, title, file_url, preview_url, description, version)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $body['project_id'],
              $body['type']        ?? 'video',
              $body['title'],
              $body['file_url']    ?? null,
              $body['preview_url'] ?? null,
              $body['description'] ?? null,
        (int) ($body['version']   ?? 1),
    ]);

    $newId   = (int) $db->lastInsertId();
    $newStmt = $db->prepare('SELECT * FROM deliverables WHERE id = ?');
    $newStmt->execute([$newId]);

    respond($newStmt->fetch(), 201);
}
