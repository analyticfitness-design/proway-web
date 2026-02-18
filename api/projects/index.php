<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$method   = requireMethods(['GET', 'POST']);
$tokenRow = verifyToken(getBearerToken());
if (!$tokenRow) respondError('Unauthorized', 401);

$isAdmin  = $tokenRow['user_type'] === 'admin';
$db       = getDB();

if ($isAdmin) {
    authenticateAdmin();
} else {
    $currentClient = authenticateClient();
}

// ── GET single project ────────────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['id'])) {
    $projectId = (int) $_GET['id'];

    $sql = 'SELECT p.*, c.name AS client_name, c.code AS client_code, c.email AS client_email
            FROM projects p
            JOIN clients c ON c.id = p.client_id
            WHERE p.id = ?';

    if (!$isAdmin) {
        $sql .= ' AND p.client_id = ?';
    }

    $stmt = $db->prepare($sql);
    $params = [$projectId];
    if (!$isAdmin) $params[] = $currentClient['id'];
    $stmt->execute($params);
    $project = $stmt->fetch();

    if (!$project) respondError('Proyecto no encontrado', 404);

    // Attach deliverables
    $dStmt = $db->prepare(
        'SELECT id, type, title, file_url, preview_url, description, version, delivered_at
         FROM deliverables WHERE project_id = ? ORDER BY delivered_at DESC'
    );
    $dStmt->execute([$projectId]);
    $project['deliverables'] = $dStmt->fetchAll();

    respond($project);
}

// ── GET list ──────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($isAdmin) {
        $where  = '1=1';
        $params = [];

        if (!empty($_GET['status'])) {
            $where .= ' AND p.status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['client_id'])) {
            $where .= ' AND p.client_id = ?';
            $params[] = (int) $_GET['client_id'];
        }

        $sql = "SELECT p.*, c.name AS client_name, c.code AS client_code,
                       (SELECT COUNT(*) FROM deliverables d WHERE d.project_id = p.id) AS deliverables_count
                FROM projects p
                JOIN clients c ON c.id = p.client_id
                WHERE $where
                ORDER BY p.created_at DESC";
    } else {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM deliverables d WHERE d.project_id = p.id) AS deliverables_count
                FROM projects p
                WHERE p.client_id = ?
                ORDER BY p.created_at DESC";
        $params = [$currentClient['id']];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    respond($stmt->fetchAll());
}

// ── POST (admin only) ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    if (!$isAdmin) respondError('Forbidden', 403);

    $body = getJsonBody();
    $required = ['client_id', 'service_type', 'price_cop'];
    foreach ($required as $r) {
        if (empty($body[$r])) respondError("Campo requerido: $r", 422);
    }

    // Auto-generate project code PW-YYYY-NNN
    $year  = date('Y');
    $cStmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE project_code LIKE ?");
    $cStmt->execute(["PW-$year-%"]);
    $num  = (int) $cStmt->fetchColumn() + 1;
    $code = sprintf('PW-%s-%03d', $year, $num);

    $stmt = $db->prepare(
        'INSERT INTO projects
         (client_id, project_code, service_type, title, description, price_cop, currency,
          start_date, deadline, notes, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int)   $body['client_id'],
                $code,
                $body['service_type'],
                $body['title']       ?? null,
                $body['description'] ?? null,
        (float) $body['price_cop'],
                $body['currency']    ?? 'COP',
                $body['start_date']  ?? null,
                $body['deadline']    ?? null,
                $body['notes']       ?? null,
                $body['status']      ?? 'cotizacion',
    ]);

    $newId   = (int) $db->lastInsertId();
    $newStmt = $db->prepare('SELECT * FROM projects WHERE id = ?');
    $newStmt->execute([$newId]);

    respond($newStmt->fetch(), 201);
}
