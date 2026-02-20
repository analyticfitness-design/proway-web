<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('GET');
$client = authenticateClient();
$db     = getDB();

$where  = 'i.client_id = ?';
$params = [$client['id']];

if (!empty($_GET['status'])) {
    $where .= ' AND i.status = ?';
    $params[] = $_GET['status'];
}

$stmt = $db->prepare(
    "SELECT i.*,
            p.project_code, p.title AS project_title, p.service_type
     FROM invoices i
     LEFT JOIN projects p ON p.id = i.project_id
     WHERE $where
     ORDER BY i.created_at DESC"
);
$stmt->execute($params);
respond($stmt->fetchAll());
