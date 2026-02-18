<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$method   = requireMethods(['GET', 'POST', 'PUT']);
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
    if ($isAdmin) {
        $where  = '1=1';
        $params = [];
        if (!empty($_GET['status'])) {
            $where .= ' AND i.status = ?';
            $params[] = $_GET['status'];
        }
        $sql = "SELECT i.*, c.name AS client_name, c.code AS client_code
                FROM invoices i
                JOIN clients c ON c.id = i.client_id
                WHERE $where
                ORDER BY i.created_at DESC";
    } else {
        $sql    = 'SELECT * FROM invoices WHERE client_id = ? ORDER BY created_at DESC';
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
    $required = ['client_id', 'amount_cop', 'total_cop'];
    foreach ($required as $r) {
        if (!isset($body[$r])) respondError("Campo requerido: $r", 422);
    }

    // Auto-generate invoice number INV-YYYY-NNN
    $year  = date('Y');
    $cStmt = $db->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number LIKE ?");
    $cStmt->execute(["INV-$year-%"]);
    $num    = (int) $cStmt->fetchColumn() + 1;
    $invNum = sprintf('INV-%s-%03d', $year, $num);

    $stmt = $db->prepare(
        'INSERT INTO invoices
         (client_id, project_id, invoice_number, amount_cop, tax_cop, total_cop,
          status, due_date, payment_method, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int)   $body['client_id'],
                $body['project_id']      ?? null,
                $invNum,
        (float) $body['amount_cop'],
        (float) ($body['tax_cop']        ?? 0),
        (float) $body['total_cop'],
                $body['status']          ?? 'pendiente',
                $body['due_date']        ?? null,
                $body['payment_method']  ?? null,
                $body['notes']           ?? null,
    ]);

    $newId   = (int) $db->lastInsertId();
    $newStmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $newStmt->execute([$newId]);

    respond($newStmt->fetch(), 201);
}

// ── PUT (admin only) ──────────────────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$isAdmin) respondError('Forbidden', 403);

    if (empty($_GET['id'])) respondError('Se requiere ?id=', 422);
    $invoiceId = (int) $_GET['id'];

    $body    = getJsonBody();
    $allowed = ['status', 'paid_at', 'payment_method', 'payu_reference', 'notes', 'due_date'];
    $update  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $update[$field] = $body[$field];
        }
    }

    if (empty($update)) respondError('No hay campos para actualizar', 422);

    // Auto-set paid_at when marking as pagada
    if (isset($update['status']) && $update['status'] === 'pagada' && empty($update['paid_at'])) {
        $update['paid_at'] = date('Y-m-d H:i:s');
    }

    $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update)));
    $values = array_values($update);
    $values[] = $invoiceId;

    $db->prepare("UPDATE invoices SET $set WHERE id = ?")->execute($values);

    $updStmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
    $updStmt->execute([$invoiceId]);
    $invoice = $updStmt->fetch();
    if (!$invoice) respondError('Factura no encontrada', 404);

    respond($invoice);
}
