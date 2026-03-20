<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Client\ClientService;
use ProWay\Domain\Invoice\InvoiceService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;
use ProWay\Infrastructure\Pdf\PdfRenderer;

class InvoiceController
{
    public function __construct(
        private readonly InvoiceService  $invoices,
        private readonly AuthMiddleware  $middleware,
        private readonly ?ClientService  $clients = null,
        private readonly ?PdfRenderer    $pdf     = null,
    ) {}

    /**
     * GET /api/v1/invoices
     */
    public function index(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);
        Response::success(['invoices' => $this->invoices->listForClient($user->id)]);
    }

    /**
     * GET /api/v1/invoices/pending
     */
    public function pending(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);
        Response::success(['invoices' => $this->invoices->getPendingForClient($user->id)]);
    }

    /**
     * POST /api/v1/invoices/{id}/pay
     * Body: { method: 'PayU'|'Stripe'|..., reference?: 'REF123' }
     */
    public function pay(Request $request, array $vars): never
    {
        $this->middleware->requireAuth($request);

        $method    = $request->input('method');
        $reference = $request->input('reference', '');

        if (empty($method)) {
            Response::error('VALIDATION', 'method is required', 422);
        }

        $ok = $this->invoices->markPaid((int) $vars['id'], $method, $reference);
        Response::success(['paid' => $ok]);
    }

    /**
     * PATCH /api/v1/invoices/{id}/status — Admin only
     * Body: { status: 'pendiente'|'enviada'|'pagada'|'vencida' }
     */
    public function updateStatus(Request $request, array $vars): never
    {
        $this->middleware->requireAdmin($request);

        $status = $request->input('status');
        if (empty($status)) {
            Response::error('VALIDATION', 'status is required', 422);
        }

        try {
            $ok = $this->invoices->updateStatus((int) $vars['id'], $status);
            Response::success(['updated' => $ok]);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION', $e->getMessage(), 422);
        }
    }

    /**
     * GET /api/v1/invoices/{id}/pdf
     * Returns a print-ready HTML page for the invoice (Save as PDF via browser).
     */
    public function downloadPdf(Request $request, array $vars): never
    {
        $user = $this->middleware->requireAuth($request);

        if ($this->clients === null || $this->pdf === null) {
            Response::error('SERVER_ERROR', 'PDF service not configured', 500);
        }

        $invoiceId = (int) $vars['id'];

        // Admin can view any invoice; client can only view their own
        $invoice = null;
        if ($user->type === 'admin') {
            $invoice = $this->invoices->getById($invoiceId);
        } else {
            $invoice = $this->invoices->getForClient($user->id, $invoiceId);
        }

        if ($invoice === null) {
            Response::error('NOT_FOUND', 'Factura no encontrada', 404);
        }

        $client = $this->clients->getById((int) $invoice['client_id']);
        if ($client === null) {
            Response::error('NOT_FOUND', 'Cliente no encontrado', 404);
        }

        $html = $this->pdf->renderInvoiceHtml($invoice, $client);

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
        echo $html;
        exit;
    }
}
