<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Invoice\InvoiceService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class InvoiceController
{
    public function __construct(
        private readonly InvoiceService  $invoices,
        private readonly AuthMiddleware  $middleware,
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
}
