<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_auth.php';

header('Content-Type: text/html; charset=utf-8');

if ($currentUser->type !== 'admin') {
    http_response_code(403);
    echo '<div class="alert alert--error">Acceso denegado.</div>';
    exit;
}

const ADMIN_INV_STATUS_MAP = [
    'borrador'  => ['class' => 'badge--neutral',   'label' => 'Borrador'],
    'pendiente' => ['class' => 'badge--pendiente',  'label' => 'Pendiente'],
    'enviada'   => ['class' => 'badge--enviada',    'label' => 'Enviada'],
    'pagada'    => ['class' => 'badge--pagada',     'label' => 'Pagada'],
    'vencida'   => ['class' => 'badge--vencido',    'label' => 'Vencida'],
    'cancelada' => ['class' => 'badge--neutral',    'label' => 'Cancelada'],
];

try {
    $invoices = $invoiceService->listAll();
} catch (Throwable $e) {
    echo '<div class="alert alert--error">Error al cargar las facturas. Inténtalo de nuevo.</div>';
    exit;
}

if (empty($invoices)) {
    echo '<p class="text-muted" style="padding: var(--pw-space-4);">No hay facturas registradas.</p>';
    exit;
}
?>
<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>N° Factura</th>
                <th>Cliente</th>
                <th>Concepto</th>
                <th>Total</th>
                <th>Estado</th>
                <th>Vencimiento</th>
                <th>PDF</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $inv):
                $status     = $inv['status'] ?? 'pendiente';
                $statusInfo = ADMIN_INV_STATUS_MAP[$status] ?? ['class' => 'badge--neutral', 'label' => ucfirst($status)];
                $number     = htmlspecialchars($inv['invoice_number'] ?? '—', ENT_QUOTES, 'UTF-8');
                $client     = htmlspecialchars($inv['client_name']    ?? $inv['client_email'] ?? '—', ENT_QUOTES, 'UTF-8');
                $concept    = htmlspecialchars($inv['notes']          ?? '—', ENT_QUOTES, 'UTF-8');
                $total      = (float) ($inv['total_cop'] ?? 0);
                $amount     = $total > 0 ? '$' . number_format($total, 0, ',', '.') : '—';
                $dueDate    = !empty($inv['due_date']) ? date('d/m/Y', strtotime($inv['due_date'])) : '—';
                $invId      = (int) ($inv['id'] ?? 0);
            ?>
            <tr>
                <td><code><?= $number ?></code></td>
                <td><?= $client ?></td>
                <td><?= $concept ?></td>
                <td><?= $amount ?></td>
                <td>
                    <span class="badge <?= htmlspecialchars($statusInfo['class'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($statusInfo['label'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </td>
                <td><?= $dueDate ?></td>
                <td>
                    <?php if ($invId > 0): ?>
                    <a href="/api/partials/invoice-pdf.php?id=<?= $invId ?>&autoprint=0"
                       target="_blank"
                       class="btn btn--ghost btn--sm"
                       title="Ver PDF">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        PDF
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
