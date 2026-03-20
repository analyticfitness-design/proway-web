<?php

declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\ActivityLog\ActivityLogService;
use ProWay\Domain\Client\ClientService;
use ProWay\Domain\Invoice\InvoiceService;
use ProWay\Domain\Notification\NotificationService;
use ProWay\Domain\Project\ProjectService;
use ProWay\Infrastructure\Email\MailjetService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

/**
 * Endpoints that require admin role.
 * Every method calls $this->requireAdmin() first.
 */
class AdminController
{
    public function __construct(
        private readonly InvoiceService       $invoices,
        private readonly ProjectService       $projects,
        private readonly ClientService        $clients,
        private readonly AuthMiddleware       $middleware,
        private readonly ?MailjetService      $mailer = null,
        private readonly ?NotificationService $notifications = null,
        private readonly ?ActivityLogService  $activityLog = null,
    ) {}

    // ── GET /api/v1/admin/clients/{id} ─────────────────────────────────────────
    public function showClient(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        $id     = (int) $vars['id'];
        $client = $this->clients->getById($id);

        if ($client === null) {
            Response::error('NOT_FOUND', 'Client not found', 404);
        }

        $projects = $this->projects->listForClient($id);
        $invoices = $this->invoices->listForClient($id);

        Response::success([
            'client'   => $client,
            'projects' => $projects,
            'invoices' => $invoices,
        ]);
    }

    // ── POST /api/v1/admin/clients ────────────────────────────────────────────
    public function createClient(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        $name     = trim((string) $request->input('name', ''));
        $email    = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($name === '' || $email === '' || $password === '') {
            Response::error('VALIDATION', 'name, email y password son requeridos', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('VALIDATION', 'email inválido', 422);
        }

        // Auto-generate unique code from email prefix
        $code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
        $code = substr($code, 0, 12) . '-' . substr(uniqid(), -4);

        try {
            $id = $this->clients->create([
                'code'      => $code,
                'name'      => $name,
                'email'     => $email,
                'phone'     => $request->input('phone') ?: null,
                'company'   => $request->input('company') ?: null,
                'plan_type' => $request->input('plan_type', 'starter'),
                'password'  => $password,
                'status'    => 'activo',
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Response::error('CONFLICT', 'Ya existe un cliente con ese email', 409);
            }
            throw $e;
        }

        // Send welcome email with credentials
        if ($this->mailer !== null) {
            $this->mailer->sendWelcome([
                'name'  => $name,
                'email' => $email,
            ]);
        }

        Response::success(['id' => $id, 'code' => $code], 201);
    }

    // ── POST /api/v1/admin/invoices ────────────────────────────────────────────
    public function createInvoice(Request $request, array $vars): never
    {
        $user = $this->requireAdmin($request);

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

        // Notify the client about the new invoice
        try {
            $this->notifications?->notify(
                'client',
                $clientId,
                'Nueva factura recibida',
                'Se ha generado una nueva factura por $' . number_format($amountCop, 0, ',', '.') . ' COP.',
                'invoice',
                '/facturas',
            );
        } catch (\Throwable) {
            // Never block primary operation
        }

        // Log to project timeline if linked to a project
        if ($projectId !== null) {
            try {
                $this->activityLog?->log(
                    $projectId,
                    'invoice_created',
                    'Factura #' . $id . ' creada por $' . number_format($amountCop, 0, ',', '.') . ' COP',
                    $user->type,
                    $user->id,
                    ['invoice_id' => $id, 'amount_cop' => $amountCop],
                );
            } catch (\Throwable) {
                // Never block primary operation
            }
        }

        Response::success(['id' => $id], 201);
    }

    // ── POST /api/v1/admin/projects ───────────────────────────────────────────
    public function createProject(Request $request, array $vars): never
    {
        $user = $this->requireAdmin($request);

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

        // Notify the client about their new project
        try {
            $title = $request->input('title') ?: $serviceType;
            $this->notifications?->notify(
                'client',
                $clientId,
                'Nuevo proyecto creado',
                "Tu proyecto \"{$title}\" ha sido creado.",
                'project',
                '/proyectos/' . $id,
            );
        } catch (\Throwable) {
            // Never block primary operation
        }

        // Log project creation to activity timeline
        try {
            $this->activityLog?->log(
                $id,
                'project_created',
                'Proyecto creado — ' . ($request->input('title') ?: $serviceType),
                $user->type,
                $user->id,
                ['service_type' => $serviceType, 'price_cop' => $priceCop],
            );
        } catch (\Throwable) {
            // Never block primary operation
        }

        Response::success(['id' => $id], 201);
    }

    // ── GET /api/v1/admin/stats ────────────────────────────────────────────────
    public function stats(Request $request, array $vars): never
    {
        $this->requireAdmin($request);

        Response::success([
            'pending_invoices'     => $this->invoices->countPending(),
            'income_this_month'    => $this->invoices->sumPaidThisMonth(),
            'active_projects'      => $this->projects->countActive(),
            'revenue_by_month'     => $this->invoices->revenueByMonth(6),
            'projects_by_status'   => $this->projects->countByStatus(),
            'clients_by_plan'      => $this->clients->countByPlan(),
            'new_clients_by_month' => $this->clients->newByMonth(6),
        ]);
    }

    private function requireAdmin(Request $request): \ProWay\Domain\Auth\UserDTO
    {
        $user = $this->middleware->requireAuth($request);
        if ($user->type !== 'admin') {
            Response::error('FORBIDDEN', 'Admin access required', 403);
        }
        return $user;
    }
}
