<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$method = requireMethods(['GET', 'PUT']);
authenticateAdmin();
$db = getDB();

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
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

    $sql = "SELECT p.*,
                   c.name  AS client_name,
                   c.code  AS client_code,
                   c.email AS client_email,
                   c.plan_type AS client_plan,
                   (SELECT COUNT(*) FROM deliverables d WHERE d.project_id = p.id) AS deliverables_count
            FROM projects p
            JOIN clients c ON c.id = p.client_id
            WHERE $where
            ORDER BY p.updated_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    respond($stmt->fetchAll());
}

// ── PUT ───────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (empty($_GET['id'])) respondError('Se requiere ?id=', 422);
    $projectId = (int) $_GET['id'];

    $body    = getJsonBody();
    $allowed = ['status', 'title', 'description', 'deadline', 'start_date',
                'price_cop', 'notes', 'delivered_at'];
    $update  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $update[$field] = $body[$field];
        }
    }

    if (empty($update)) respondError('No hay campos para actualizar', 422);

    // Auto-set delivered_at when status = entregado
    if (isset($update['status']) && $update['status'] === 'entregado' && empty($update['delivered_at'])) {
        $update['delivered_at'] = date('Y-m-d H:i:s');
    }

    $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update)));
    $values = array_values($update);
    $values[] = $projectId;

    $db->prepare("UPDATE projects SET $set WHERE id = ?")->execute($values);

    $updStmt = $db->prepare(
        'SELECT p.*, c.name AS client_name, c.code AS client_code
         FROM projects p JOIN clients c ON c.id = p.client_id
         WHERE p.id = ?'
    );
    $updStmt->execute([$projectId]);
    $project = $updStmt->fetch();
    if (!$project) respondError('Proyecto no encontrado', 404);

    respond($project);
}
