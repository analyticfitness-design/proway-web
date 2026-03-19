<?php

declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Invoice\InvoiceService;
use ProWay\Domain\Project\ProjectService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

/**
 * Endpoints that require admin role.
 * Every method calls $this->requireAdmin() first.
 */
class AdminController
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly ProjectService $projects,
        private readonly AuthMiddleware $middleware,
    ) {}

    // ── POST /api/v1/admin/invoices ────────────────────────────────────────────
    public function createInvoice(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        $clientId  = (int) $request->input('client_id', 0);
        $amountCop = (float) $request->input('amount_cop', 0);
        $taxCop    = (float) $request->input('tax_cop', 0);
        $dueDate   = $request->input('due_date', '');
        $notes     = $request->input('notes', '');
        $projectId = $request->input('project_id') ? (int) $request->input('project_id') : null;

        if ($clientId === 0 || $amountCop <= 0) {
            Response::error('VALIDATION', 'client_id and amount_cop are required', 422);
        }

        $id = $this->invoices->create([
            'client_id'  => $clientId,
            'project_id' => $projectId,
            'amount_cop' => $amountCop,
            'tax_cop'    => $taxCop,
            'due_date'   => $dueDate ?: null,
            'notes'      => $notes ?: null,
            'status'     => 'enviada',
        ]);

        Response::success(['id' => $id], 201);
    }

    // ── POST /api/v1/admin/projects ───────────────────────────────────────────
    public function createProject(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        $clientId    = (int) $request->input('client_id', 0);
        $serviceType = (string) $request->input('service_type', '');
        $priceCop    = (float) $request->input('price_cop', 0);

        if ($clientId === 0 || $serviceType === '' || $priceCop <= 0) {
            Response::error('VALIDATION', 'client_id, service_type y price_cop son requeridos', 422);
        }

        $id = $this->projects->create([
            'client_id'    => $clientId,
            'service_type' => $serviceType,
            'title'        => $request->input('title')       ?: null,
            'description'  => $request->input('description') ?: null,
            'price_cop'    => $priceCop,
            'status'       => $request->input('status', 'cotizacion'),
            'start_date'   => $request->input('start_date')  ?: null,
            'deadline'     => $request->input('deadline')    ?: null,
            'notes'        => $request->input('notes')       ?: null,
        ]);

        Response::success(['id' => $id], 201);
    }

    // ── GET /api/v1/admin/stats ────────────────────────────────────────────────
    public function stats(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        Response::success([
            'pending_invoices'   => $this->invoices->countPending(),
            'income_this_month'  => $this->invoices->sumPaidThisMonth(),
            'active_projects'    => $this->projects->countActive(),
        ]);
    }

    private function requireAdmin(Request $request): void
    {
        $user = $this->middleware->requireAuth($request);
        if ($user->type !== 'admin') {
            Response::error('FORBIDDEN', 'Admin access required', 403);
        }
    }
}
