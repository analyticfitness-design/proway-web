<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/database.php';

$method = requireMethods(['GET', 'PUT']);
authenticateAdmin();
$db = getDB();

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $where  = '1=1';
    $params = [];

    if (!empty($_GET['status'])) {
        $where .= ' AND i.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['client_id'])) {
        $where .= ' AND i.client_id = ?';
        $params[] = (int) $_GET['client_id'];
    }

    $sql = "SELECT i.*,
                   c.name  AS client_name,
                   c.code  AS client_code,
                   c.email AS client_email
            FROM invoices i
            JOIN clients c ON c.id = i.client_id
            WHERE $where
            ORDER BY i.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    respond($stmt->fetchAll());
}

// ── PUT: marcar pagada / actualizar estado ────────────────────────────────────
if ($method === 'PUT') {
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

    if (isset($update['status']) && $update['status'] === 'pagada' && empty($update['paid_at'])) {
        $update['paid_at'] = date('Y-m-d H:i:s');
    }

    $set    = implode(', ', array_map(fn($k) => "$k = ?", array_keys($update)));
    $values = array_values($update);
    $values[] = $invoiceId;

    $db->prepare("UPDATE invoices SET $set WHERE id = ?")->execute($values);

    $updStmt = $db->prepare(
        'SELECT i.*, c.name AS client_name, c.code AS client_code, c.email AS client_email
         FROM invoices i JOIN clients c ON c.id = i.client_id
         WHERE i.id = ?'
    );
    $updStmt->execute([$invoiceId]);
    $invoice = $updStmt->fetch();
    if (!$invoice) respondError('Factura no encontrada', 404);

    // Notify on invoice sent
    if (isset($update['status']) && $update['status'] === 'enviada') {
        sendNotification('invoice_sent', [
            'client_name'    => $invoice['client_name'],
            'client_email'   => $invoice['client_email'],
            'invoice_number' => $invoice['invoice_number'],
            'total_cop'      => $invoice['total_cop'],
            'due_date'       => $invoice['due_date'],
        ]);
    }

    // Notify on payment marked as received
    if (isset($update['status']) && $update['status'] === 'pagada') {
        sendNotification('payment_received', [
            'client_name'    => $invoice['client_name'],
            'client_email'   => $invoice['client_email'],
            'invoice_number' => $invoice['invoice_number'],
            'total_cop'      => $invoice['total_cop'],
            'payment_method' => $invoice['payment_method'] ?? 'Manual',
            'reference'      => $invoice['payu_reference'] ?? '—',
        ]);
    }

    respond($invoice);
}
