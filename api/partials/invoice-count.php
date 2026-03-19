<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $invoices = $invoiceService->listForClient($currentUser->id);
    $pending  = array_filter(
        $invoices,
        static fn(array $i): bool => in_array($i['status'] ?? '', ['pendiente', 'enviada'], true)
    );
    $count = count($pending);
} catch (Throwable) {
    echo '';
    exit;
}

if ($count > 0) {
    echo '<span class="badge badge--pendiente" title="' . $count . ' factura(s) por pagar">' . $count . '</span>';
}
