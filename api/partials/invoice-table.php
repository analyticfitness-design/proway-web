<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

// Map invoice status values to CSS modifier classes and display labels.
const INVOICE_STATUS_MAP = [
    'pendiente' => ['class' => 'badge--pendiente', 'label' => 'Pendiente'],
    'enviada'   => ['class' => 'badge--enviada',   'label' => 'Enviada'],
    'pagada'    => ['class' => 'badge--pagada',    'label' => 'Pagada'],
    'vencida'   => ['class' => 'badge--vencido',   'label' => 'Vencida'],
];

try {
    $invoices = $invoiceService->listForClient($currentUser->id);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar las facturas. Inténtalo de nuevo.</div>';
    exit;
}

if (empty($invoices)) {
    echo '<p class="text-muted">No tienes facturas registradas en este momento.</p>';
    exit;
}
?>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Monto</th>
            <th>Status</th>
            <th>Vencimiento</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($invoices as $invoice):
            $status     = $invoice['status'] ?? 'pendiente';
            $statusInfo = INVOICE_STATUS_MAP[$status] ?? ['class' => 'badge--pendiente', 'label' => ucfirst($status)];
            $number     = htmlspecialchars($invoice['invoice_number'] ?? $invoice['id'] ?? '—', ENT_QUOTES, 'UTF-8');
            $amount     = isset($invoice['amount'])
                ? '$' . number_format((float) $invoice['amount'], 0, ',', '.')
                : '—';
            $rawDue     = $invoice['due_date'] ?? '';
            $dueDate    = $rawDue !== '' ? date('d/m/Y', strtotime($rawDue)) : '—';
        ?>
        <tr>
            <td><?= $number ?></td>
            <td><?= $amount ?></td>
            <td>
                <span class="badge <?= htmlspecialchars($statusInfo['class'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </td>
            <td><?= $dueDate ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
