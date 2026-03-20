<?php
declare(strict_types=1);

namespace ProWay\Domain\WhatsApp;

use ProWay\Domain\Client\ClientService;
use ProWay\Infrastructure\WhatsApp\WhatsAppService;

/**
 * High-level WhatsApp notification dispatcher.
 *
 * Each public method silently fails — WhatsApp notifications
 * must never block the primary business operation.
 */
class WhatsAppNotifier
{
    public function __construct(
        private readonly WhatsAppService $wa,
        private readonly ClientService   $clients,
    ) {}

    /**
     * Notify a client that their project status changed.
     */
    public function notifyProjectStatusChange(int $clientId, string $projectName, string $newStatus): void
    {
        $client = $this->resolveClient($clientId);
        if ($client === null) {
            return;
        }

        $statusLabels = [
            'cotizacion'     => 'Cotizacion',
            'confirmado'     => 'Confirmado',
            'en_produccion'  => 'En produccion',
            'revision'       => 'En revision',
            'entregado'      => 'Entregado',
            'facturado'      => 'Facturado',
            'pagado'         => 'Pagado',
        ];

        $label = $statusLabels[$newStatus] ?? $newStatus;
        $name  = $client['name'] ?? 'Cliente';

        $text = "Hola {$name}, tu proyecto \"{$projectName}\" ha cambiado de estado a: *{$label}*.\n\n"
              . "Revisa los detalles en tu portal: https://prowaylab.com/portal";

        $this->wa->sendText($client['wa_phone'], $text);
    }

    /**
     * Notify a client about a new invoice.
     */
    public function notifyNewInvoice(int $clientId, string $invoiceNumber, string $amount, string $payUrl): void
    {
        $client = $this->resolveClient($clientId);
        if ($client === null) {
            return;
        }

        $name = $client['name'] ?? 'Cliente';

        $text = "Hola {$name}, se ha generado una nueva factura:\n\n"
              . "Factura: *#{$invoiceNumber}*\n"
              . "Monto: *\${$amount} COP*\n\n"
              . "Puedes verla y pagarla desde tu portal:\n{$payUrl}";

        $this->wa->sendText($client['wa_phone'], $text);
    }

    /**
     * Notify a client that a deliverable was uploaded to their project.
     */
    public function notifyDeliverableUploaded(int $clientId, string $projectName, string $deliverableName): void
    {
        $client = $this->resolveClient($clientId);
        if ($client === null) {
            return;
        }

        $name = $client['name'] ?? 'Cliente';

        $text = "Hola {$name}, se ha subido un nuevo entregable a tu proyecto \"{$projectName}\":\n\n"
              . "*{$deliverableName}*\n\n"
              . "Descargalo desde tu portal: https://prowaylab.com/portal";

        $this->wa->sendText($client['wa_phone'], $text);
    }

    // ── Private helpers ─────────────────────────────────────────────────────────

    /**
     * Look up the client, check they have WhatsApp enabled, and return their data.
     * Returns null if client not found, no phone, or notifications disabled.
     */
    private function resolveClient(int $clientId): ?array
    {
        $client = $this->clients->getById($clientId);

        if ($client === null) {
            return null;
        }

        $phone = $client['wa_phone'] ?? '';
        $enabled = (int) ($client['wa_notifications'] ?? 1);

        if ($phone === '' || $enabled !== 1) {
            return null;
        }

        return $client;
    }
}
