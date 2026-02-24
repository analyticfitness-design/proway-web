<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/database.php';

requireMethod('POST');

$body = getJsonBody();

// ── Validate PayU signature ───────────────────────────────────────────────────
// PayU signs with: md5(apiKey~merchantId~referenceCode~amount~currency~statePol)
$apiKey      = PAYU_API_KEY;
$merchantId  = $body['merchant_id']    ?? '';
$reference   = $body['reference_sale'] ?? '';
$amount      = $body['value']          ?? '';
$currency    = $body['currency']       ?? 'COP';
$statePol    = $body['state_pol']      ?? '';
$receivedSig = strtolower($body['sign'] ?? '');

$computed = strtolower(md5("$apiKey~$merchantId~$reference~$amount~$currency~$statePol"));

if (!hash_equals($computed, $receivedSig)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid signature']);
    exit;
}

// ── Only process approved (state_pol = 4) ────────────────────────────────────
if ((int) $statePol !== 4) {
    respond(['message' => 'Transaction not approved, ignored']);
}

$db = getDB();

// Reference format: pw_{code}_{timestamp}  e.g. pw_pw-001_1718000000
if (!preg_match('/^pw_(pw-\d+)_\d+$/', $reference, $matches)) {
    respond(['message' => 'Reference format unknown, ignored']);
}

$clientCode = $matches[1];

// Find client
$stmt = $db->prepare('SELECT * FROM clients WHERE code = ?');
$stmt->execute([$clientCode]);
$client = $stmt->fetch();

if ($client) {
    // Mark matching pending invoice as pagada
    $invStmt = $db->prepare(
        "SELECT * FROM invoices
         WHERE client_id = ? AND status IN ('pendiente','enviada')
         ORDER BY due_date ASC LIMIT 1"
    );
    $invStmt->execute([$client['id']]);
    $invoice = $invStmt->fetch();

    if ($invoice) {
        $db->prepare(
            "UPDATE invoices SET status = 'pagada', paid_at = NOW(),
             payment_method = 'PayU', payu_reference = ?
             WHERE id = ?"
        )->execute([$reference, $invoice['id']]);

        // Notify payment received
        sendNotification('payment_received', [
            'client_name'    => $client['name'],
            'client_email'   => $client['email'],
            'invoice_number' => $invoice['invoice_number'],
            'total_cop'      => $invoice['total_cop'],
            'payment_method' => 'PayU',
            'reference'      => $reference,
        ]);
    }

    // Activate client if they were a prospect
    if ($client['status'] === 'prospecto') {
        $db->prepare("UPDATE clients SET status = 'activo' WHERE id = ?")->execute([$client['id']]);
    }

} else {
    // New client via payment — create with minimal data
    $buyerEmail = $body['buyer_email'] ?? '';
    $buyerName  = $body['buyer_full_name'] ?? ('Cliente ' . $clientCode);

    if ($buyerEmail) {
        $num  = substr($clientCode, 3); // extract "001" from "pw-001"
        $codeToUse = 'pw-' . $num;

        // Insert new client
        $insStmt = $db->prepare(
            'INSERT IGNORE INTO clients (code, name, email, plan_type, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insStmt->execute([$codeToUse, $buyerName, $buyerEmail, 'video_individual', 'activo']);
        $newClientId = (int) $db->lastInsertId();

        if ($newClientId) {
            // Create default profile with temp password
            $hash = password_hash('pw2026', PASSWORD_BCRYPT);
            $db->prepare(
                'INSERT INTO client_profiles (client_id, password_hash) VALUES (?, ?)'
            )->execute([$newClientId, $hash]);

            // Create invoice record
            $year   = date('Y');
            $cStmt2 = $db->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number LIKE ?");
            $cStmt2->execute(["INV-$year-%"]);
            $invNum  = sprintf('INV-%s-%03d', $year, (int) $cStmt2->fetchColumn() + 1);
            $amtCop  = (float) $amount;

            $db->prepare(
                'INSERT INTO invoices
                 (client_id, invoice_number, amount_cop, total_cop, status, paid_at,
                  payment_method, payu_reference)
                 VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)'
            )->execute([$newClientId, $invNum, $amtCop, $amtCop, 'pagada', 'PayU', $reference]);
        }
    }
}

respond(['message' => 'Webhook processed']);
