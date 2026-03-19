<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

const INVOICE_STATUS_MAP = [
    'borrador'  => ['class' => 'badge--neutral',   'label' => 'Borrador'],
    'pendiente' => ['class' => 'badge--pendiente',  'label' => 'Pendiente'],
    'enviada'   => ['class' => 'badge--enviada',    'label' => 'Enviada'],
    'pagada'    => ['class' => 'badge--pagada',     'label' => 'Pagada'],
    'vencida'   => ['class' => 'badge--vencido',    'label' => 'Vencida'],
    'cancelada' => ['class' => 'badge--neutral',    'label' => 'Cancelada'],
];

/** Statuses where the client can initiate payment */
const PAYABLE_STATUSES = ['pendiente', 'enviada'];

try {
    $invoices = $invoiceService->listForClient($currentUser->id);
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar las facturas. Inténtalo de nuevo.</div>';
    exit;
}

if (empty($invoices)) {
    echo '<p class="text-muted" style="padding: var(--pw-space-4);">No tienes facturas registradas en este momento.</p>';
    exit;
}
?>
<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>N° Factura</th>
                <th>Concepto</th>
                <th>Fecha</th>
                <th>Monto</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $invoice):
                $status     = $invoice['status'] ?? 'pendiente';
                $statusInfo = INVOICE_STATUS_MAP[$status] ?? ['class' => 'badge--neutral', 'label' => ucfirst($status)];
                $number     = htmlspecialchars($invoice['invoice_number'] ?? ('INV-' . $invoice['id']), ENT_QUOTES, 'UTF-8');
                $concept    = htmlspecialchars($invoice['notes'] ?? 'Servicio ProWay Lab', ENT_QUOTES, 'UTF-8');
                $total      = (float) ($invoice['total_cop'] ?? $invoice['amount_cop'] ?? 0);
                $amount     = $total > 0 ? '$' . number_format($total, 0, ',', '.') : '—';
                $rawDate    = $invoice['created_at'] ?? '';
                $fecha      = $rawDate !== '' ? date('d/m/Y', strtotime($rawDate)) : '—';
                $invoiceId  = (int) ($invoice['id'] ?? 0);
                $canPay     = in_array($status, PAYABLE_STATUSES, true) && $invoiceId > 0;
            ?>
            <tr>
                <td><code><?= $number ?></code></td>
                <td><?= $concept ?></td>
                <td><?= $fecha ?></td>
                <td><?= $amount ?></td>
                <td>
                    <span class="badge <?= htmlspecialchars($statusInfo['class'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td>
                    <?php if ($canPay): ?>
                    <button
                        class="btn btn--primary btn--sm"
                        onclick="wompiPay(<?= $invoiceId ?>, this)"
                        title="Pagar con Wompi">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        Pagar
                    </button>
                    <?php else: ?>
                    <span class="text-muted" style="font-size: 0.75rem;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
