<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');
$client = authenticateClient();
$db     = getDB();

$where  = 'p.client_id = ?';
$params = [$client['id']];

if (!empty($_GET['status'])) {
    $where .= ' AND p.status = ?';
    $params[] = $_GET['status'];
}

$stmt = $db->prepare(
    "SELECT p.*,
            (SELECT COUNT(*) FROM deliverables d WHERE d.project_id = p.id) AS deliverables_count
     FROM projects p
     WHERE $where
     ORDER BY p.updated_at DESC"
);
$stmt->execute($params);
respond($stmt->fetchAll());
