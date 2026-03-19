<?php

declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Invoice\InvoiceService;
use ProWay\Domain\Client\ClientService;
use ProWay\Domain\Payment\WompiService;
use ProWay\Infrastructure\Email\MailjetService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class PaymentController
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly ClientService  $clients,
        private readonly WompiService   $wompi,
        private readonly MailjetService $mailer,
        private readonly AuthMiddleware $middleware,
    ) {}

    /**
     * POST /api/v1/payments/checkout  (client auth required)
     *
     * Body JSON: { "invoice_id": 42 }
     *
     * Returns the Wompi widget payload: public_key, reference,
     * amount_in_cents, currency, signature.integrity.
     * The frontend embeds these as data-* attributes on the Wompi script tag.
     */
    public function checkout(Request $request, array $vars): never
    {
        $user      = $this->middleware->requireAuth($request);
        $invoiceId = (int) $request->input('invoice_id', 0);

        if ($invoiceId === 0) {
            Response::error('VALIDATION', 'invoice_id is required', 422);
        }

        $invoice = $this->invoices->getForClient($user->id, $invoiceId);

        if ($invoice === null) {
            Response::error('NOT_FOUND', 'Invoice not found', 404);
        }

        if ($invoice['status'] === 'pagada') {
            Response::error('CONFLICT', 'Invoice already paid', 409);
        }

        $client    = $this->clients->getById($user->id);
        $reference = 'PW-' . $invoiceId . '-' . time();
        $amountCOP = (int) $invoice['amount'];

        $checkout = $this->wompi->buildCheckoutData(
            $reference,
            $amountCOP,
            $client['email'] ?? ''
        );

        Response::success(['checkout' => $checkout]);
    }

    /**
     * POST /api/v1/payments/webhook  (public — verified by X-Event-Checksum)
     *
     * Receives Wompi transaction events.
     * On APPROVED status: marks invoice as paid and sends confirmation email.
     */
    public function webhook(Request $request, array $vars): never
    {
        $rawBody  = file_get_contents('php://input');
        $checksum = $_SERVER['HTTP_X_EVENT_CHECKSUM'] ?? '';
        $event    = json_decode($rawBody ?: '', true) ?? [];

        if (empty($event)) {
            Response::error('BAD_REQUEST', 'Invalid payload', 400);
        }

        if (!$this->wompi->verifyWebhookSignature($event, $checksum)) {
            Response::error('UNAUTHORIZED', 'Invalid signature', 401);
        }

        $tx = $this->wompi->parseWebhookEvent($event);

        if ($tx['status'] !== 'APPROVED') {
            Response::success(['received' => true]);
        }

        // Reference format: PW-{invoiceId}-{timestamp}
        $parts     = explode('-', $tx['reference']);
        $invoiceId = isset($parts[1]) ? (int) $parts[1] : 0;

        if ($invoiceId > 0) {
            $this->invoices->markPaid($invoiceId, 'wompi', $tx['id']);

            $invoice = $this->invoices->getById($invoiceId);
            if ($invoice !== null) {
                $client = $this->clients->getById((int) $invoice['client_id']);
                if ($client !== null) {
                    $this->mailer->sendPaymentConfirmation($invoice, $client);
                }
            }
        }

        Response::success(['received' => true]);
    }
}
